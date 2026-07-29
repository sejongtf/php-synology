<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Message;

use Closure;
use Psr\Http\Message\ResponseInterface as PsrResponse;
use Sejongtf\Synology\Exceptions\ApiException;
use Sejongtf\Synology\Exceptions\RequestException;
use stdClass;

/**
 * PSR-7 응답을 DSM 응답 봉투로 읽는다.
 *
 * ```
 * {"success": true,  "data":  { ... }}
 * {"success": false, "error": {"code": 1100, "errors": [{"code": 408, "path": "/test/:"}]}}
 * ```
 *
 * 본문은 **JSON/plaintext 일 때만** 읽는다. 다운로드처럼 큰 바이너리 응답에서
 * 본문을 통째로 메모리에 올리지 않기 위해서다. 바이너리 본문이 필요하면
 * `toPsrResponse()->getBody()` 로 스트림을 직접 다룬다.
 */
final class Response
{
    private ?string $body = null;

    private bool $decoded = false;

    private ?stdClass $payload = null;

    /**
     * @param  Closure|null  $exceptionFactory  `fn (PsrResponse, array $error): ApiException`.
     *                                          어느 API 를 불렀는지에 따라 코드 표가 달라지므로
     *                                          예외 선택은 요청을 보낸 쪽(`Http\ErrorMapper`)이 정한다.
     *                                          생략하면 공통 표만 쓰는 `ApiException` 이다.
     */
    public function __construct(
        private readonly PsrResponse $psr,
        private readonly ?Closure $exceptionFactory = null,
    ) {}

    public function contentType(): ?string
    {
        return $this->psr->getHeaderLine('Content-Type') ?: null;
    }

    public function isJsonOrPlaintext(): bool
    {
        $contentType = (string) $this->contentType();

        return str_contains($contentType, 'application/json')
            || str_contains($contentType, 'text/plain');
    }

    /**
     * 봉투로 읽히면 `success` 를, 아니면 HTTP 상태 코드를 본다.
     *
     * 상태 코드 폴백이 필요한 이유: `SYNO.FileStation.Download` 를 `mode=open` 으로
     * 부르면 실패 시 JSON 봉투가 아니라 HTTP 404 가 온다.
     *
     * 다만 `application/json` 이라고 해놓고 봉투로 파싱되지 않으면 상태 코드를 믿지 않는다.
     * 200 과 함께 깨진 본문이 오는 경우가 있는데, 이때 성공으로 읽으면
     * `isJsonOrPlaintext()` 로 걸러낸 뒤 `throw()` 하는 바이너리 엔드포인트
     * (`Download`, `Chat\Api\External::post_file_get`)가 오류 응답을 파일로 착각한다.
     *
     * `text/plain` 은 다르게 본다. DSM 이 텍스트 파일 다운로드에 쓰는 타입이라
     * 파싱 실패가 곧 오류를 뜻하지 않는다.
     */
    public function success(): bool
    {
        if ($payload = $this->payload()) {
            return ($payload->success ?? false) === true;
        }

        if ($this->declaresJson()) {
            return false;
        }

        return $this->psr->getStatusCode() < 400;
    }

    public function body(): string
    {
        if ($this->body === null) {
            $stream = $this->psr->getBody();

            if ($stream->isSeekable()) {
                $stream->rewind();
            }

            $this->body = (string) $stream;
        }

        return $this->body;
    }

    /**
     * 성공 응답의 `data`. 키 자체가 없으면 null 이라 빈 데이터(`[]`)와 구분된다.
     *
     * 예전에는 `ResponseData` 값 객체로 감싸 돌려줬는데, 호출부가 하나같이 곧바로
     * `toArray()` 로 벗기고 있었고 `get()`/`has()` 는 부르는 곳이 없었다. PHP 배열은
     * 값 타입이라 그 객체가 지키던 불변성도 배열이 이미 갖고 있다.
     *
     * @return array<mixed>|null
     */
    public function data(): ?array
    {
        if (! $this->hasData()) {
            return null;
        }

        $raw = $this->dataRaw();

        return is_array($raw) ? $raw : (array) $raw;
    }

    /**
     * 성공 응답에 `data` 키가 실제로 있는지. 성공+빈 데이터와 data 누락을 구분한다.
     */
    public function hasData(): bool
    {
        $payload = $this->payload();

        return $payload !== null
            && $this->success()
            && property_exists($payload, 'data');
    }

    public function dataRaw(): mixed
    {
        $payload = $this->payload();

        if ($payload === null || ! property_exists($payload, 'data')) {
            return null;
        }

        return self::normalize($payload->data);
    }

    /**
     * 원본 `data` 가 JSON 객체(`{}`)인지. 연관배열로 디코드하면 `{}` 와 `[]` 를
     * 구분할 수 없으므로 객체 형태로 디코드한 값을 그대로 본다.
     */
    public function dataIsObject(): bool
    {
        return $this->payload()?->data instanceof stdClass;
    }

    /**
     * 실패 응답의 `error`.
     *
     * 바깥 `code` 와 `errors[].code` 는 **서로 다른 코드 테이블**이다. 예를 들어
     * `{"code":1100,"errors":[{"code":408,"path":"/test/:"}]}` 에서 1100 은 CopyMove
     * 계열 코드고 408 은 파일 연산 코드(No such file or directory)다.
     *
     * `errors` 는 상세 정보가 있을 때만 응답에 키가 생긴다. 없는 것과 빈 것을
     * 구분해야 하므로 없으면 키를 만들지 않는다.
     *
     * @return array{code: int, errors?: array<int, array<string, mixed>>}|null
     */
    public function error(): ?array
    {
        $payload = $this->payload();

        if ($payload === null || ! property_exists($payload, 'error')) {
            return null;
        }

        $error = self::normalize($payload->error);

        if (! is_array($error)) {
            return null;
        }

        $normalized = ['code' => (int) ($error['code'] ?? 0)];

        if (is_array($error['errors'] ?? null)) {
            $normalized['errors'] = $error['errors'];
        }

        return $normalized;
    }

    /**
     * 실패 응답의 DSM 오류 코드. 오류 봉투가 아니면 null.
     *
     * 코드만 보고 분기하는 자리가 많다(세션 만료 판정, 예외 클래스 선택).
     */
    public function errorCode(): ?int
    {
        return $this->error()['code'] ?? null;
    }

    /**
     * @return $this
     *
     * @throws RequestException
     */
    public function throw(): static
    {
        if ($this->success()) {
            return $this;
        }

        if ($error = $this->error()) {
            throw $this->exceptionFactory
                ? ($this->exceptionFactory)($this->psr, $error)
                : new ApiException($this->psr, $error['code']);
        }

        // JSON 봉투가 아니거나 error 가 없는 실패 — HTTP 수준의 오류로 올린다.
        throw RequestException::make($this->psr);
    }

    public function header(string $header): string
    {
        return $this->psr->getHeaderLine($header);
    }

    public function toPsrResponse(): PsrResponse
    {
        return $this->psr;
    }

    /**
     * Content-Type 이 명시적으로 JSON 인지. `text/plain` 은 포함하지 않는다.
     */
    private function declaresJson(): bool
    {
        return str_contains((string) $this->contentType(), 'application/json');
    }

    /**
     * 봉투를 한 번만 디코드한다. JSON 이 아니거나 최상위가 객체가 아니면 null.
     */
    private function payload(): ?stdClass
    {
        if ($this->decoded) {
            return $this->payload;
        }

        $this->decoded = true;

        if (! $this->isJsonOrPlaintext()) {
            return null;
        }

        $value = json_decode($this->body());

        return $this->payload = $value instanceof stdClass ? $value : null;
    }

    /**
     * stdClass 트리를 배열로 바꾼다. 스칼라와 빈 배열은 그대로 둔다.
     */
    private static function normalize(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            $value = (array) $value;
        }

        if (is_array($value)) {
            return array_map(self::normalize(...), $value);
        }

        return $value;
    }
}
