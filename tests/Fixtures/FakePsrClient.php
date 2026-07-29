<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Tests\Fixtures;

use Nyholm\Psr7\Response as PsrResponse;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * 나간 요청을 기록하고 미리 지정한 응답을 돌려주는 PSR-18 구현.
 */
class FakePsrClient implements ClientInterface
{
    /** @var array<int, RequestInterface> */
    public array $requests = [];

    private ?ClientExceptionInterface $failure = null;

    /** @var array<int, ResponseInterface> */
    private array $responses;

    public function __construct(?ResponseInterface $response = null)
    {
        $this->responses = [
            $response ?? new PsrResponse(200, ['Content-Type' => 'application/json'], '{"success":true,"data":{}}'),
        ];
    }

    public static function json(string $body, int $status = 200): self
    {
        return new self(new PsrResponse($status, ['Content-Type' => 'application/json'], $body));
    }

    /**
     * 요청 순서대로 돌려줄 JSON 본문들. 목록이 바닥나면 마지막 것을 계속 쓴다.
     */
    public static function sequence(string ...$bodies): self
    {
        $client = new self;
        $client->responses = array_map(
            static fn (string $body) => new PsrResponse(200, ['Content-Type' => 'application/json'], $body),
            $bodies,
        );

        return $client;
    }

    /**
     * 전송 자체가 실패하는 상황(DNS, 연결 거부 등)을 흉내낸다.
     */
    public function failWith(string $message): self
    {
        $this->failure = new class($message) extends RuntimeException implements ClientExceptionInterface {};

        return $this;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        if ($this->failure) {
            throw $this->failure;
        }

        return count($this->responses) > 1 ? array_shift($this->responses) : $this->responses[0];
    }

    /**
     * 지금까지 나간 요청들의 `api` 파라미터.
     *
     * @return array<int, string>
     */
    public function calledApis(): array
    {
        return array_map(
            static fn (RequestInterface $request): string => (string) (self::paramsOf($request)['api'] ?? ''),
            $this->requests,
        );
    }

    public function lastRequest(): RequestInterface
    {
        return $this->requests[array_key_last($this->requests)];
    }

    /**
     * 마지막 요청의 쿼리스트링을 배열로. `SynoToken` 이 있을 때만 비어 있지 않다.
     *
     * @return array<string, string>
     */
    public function lastQuery(): array
    {
        return self::queryOf($this->lastRequest());
    }

    /**
     * 마지막 요청의 form body 를 배열로. `SynoToken` 을 뺀 요청 전체가 여기로 나간다.
     *
     * @return array<string, string>
     */
    public function lastBody(): array
    {
        return self::bodyOf($this->lastRequest());
    }

    /**
     * 쿼리든 본문이든 요청에 실린 값 전부. "어디에 실렸든 나가긴 했는가" 만 볼 때 쓴다.
     *
     * @return array<string, string>
     */
    public function lastParams(): array
    {
        return self::paramsOf($this->lastRequest());
    }

    /**
     * @return array<string, string>
     */
    public static function queryOf(RequestInterface $request): array
    {
        return self::asArray($request->getUri()->getQuery());
    }

    /**
     * @return array<string, string>
     */
    public static function bodyOf(RequestInterface $request): array
    {
        return self::asArray((string) $request->getBody());
    }

    /**
     * @return array<string, string>
     */
    public static function paramsOf(RequestInterface $request): array
    {
        return self::queryOf($request) + self::bodyOf($request);
    }

    /**
     * @return array<string, string>
     */
    private static function asArray(string $urlencoded): array
    {
        parse_str($urlencoded, $parsed);

        /** @var array<string, string> $parsed */
        return $parsed;
    }
}
