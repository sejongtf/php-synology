<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Registry;

/**
 * `SYNO.API.Info` 응답을 담아 두고 경로와 버전을 해석한다.
 *
 * 왜 필요한가: DSM 7 에서 대부분의 API 가 `entry.cgi` 로 통합됐지만 전부는 아니다.
 * 공식 문서 예시에도 `SYNO.VideoStation.Info → VideoStation/info.cgi` 가 남아 있고,
 * 구형 DSM 은 `auth.cgi` / `FileStation/file_share.cgi` 를 쓴다.
 * 그래서 경로는 조회해서 알아내야 하고 `entry.cgi` 는 안전한 기본값일 뿐이다.
 *
 * 레지스트리가 비어 있어도 동작한다 — 그 경우 전부 기본값으로 떨어진다.
 */
final class ApiRegistry
{
    public const DEFAULT_PATH = 'entry.cgi';

    /**
     * @param  array<string, array{path?: string, minVersion?: int, maxVersion?: int, requestFormat?: string}>  $apis
     */
    public function __construct(private array $apis = []) {}

    /**
     * `SYNO.API.Info` 의 `query` 응답에서 만든다.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromInfoResponse(array $data): self
    {
        $apis = [];

        foreach ($data as $name => $info) {
            if (! is_string($name) || ! is_array($info)) {
                continue;
            }

            $apis[$name] = $info;
        }

        return new self($apis);
    }

    public function has(string $api): bool
    {
        return isset($this->apis[$api]);
    }

    /**
     * `/webapi/` 아래의 상대 경로. 모르는 API 는 `entry.cgi` 로 본다.
     */
    public function path(string $api): string
    {
        $path = $this->apis[$api]['path'] ?? null;

        return is_string($path) && $path !== '' ? $path : self::DEFAULT_PATH;
    }

    public function minVersion(string $api): ?int
    {
        $value = $this->apis[$api]['minVersion'] ?? null;

        return is_int($value) ? $value : null;
    }

    public function maxVersion(string $api): ?int
    {
        $value = $this->apis[$api]['maxVersion'] ?? null;

        return is_int($value) ? $value : null;
    }

    /**
     * `requestFormat` 이 "JSON" 인지.
     *
     * 문서상으로는 이 값이 "JSON" 이면 그 API 의 파라미터를 전부 JSON 인코딩하라는 뜻이고,
     * 실제로 공식 예시도 문자열을 따옴표로 감싼다(`taskid="51CB..."`, `mode="open"`).
     *
     * 다만 **실기기 확인 결과 날것 문자열로도 DSM 이 정상 처리**한다. 그래서
     * `Api::raw()` 와 `Http\Connection::prepare()` 는 배열만 인코딩한다. 이 메서드는
     * 앞으로 전면 인코딩이 필요한 API 를 만났을 때를 위해 남겨 둔 것이다.
     */
    public function requiresJsonParams(string $api): bool
    {
        $format = $this->apis[$api]['requestFormat'] ?? null;

        return is_string($format) && strtoupper($format) === 'JSON';
    }

    /**
     * 서버가 지원하는 범위 안으로 버전을 맞춘다.
     *
     * 범위를 벗어난 버전을 그대로 보내면 DSM 은 104(The requested version does not
     * support the functionality)를 돌려준다. 알 수 없는 API 면 요청한 값을 그대로 쓴다.
     *
     * @param  int|null  $version  원하는 버전. null 이면 서버 최대 버전.
     */
    public function negotiate(string $api, ?int $version = null): ?int
    {
        $min = $this->minVersion($api);
        $max = $this->maxVersion($api);

        if ($version === null) {
            return $max;
        }

        if ($max !== null && $version > $max) {
            return $max;
        }

        if ($min !== null && $version < $min) {
            return $min;
        }

        return $version;
    }

    /**
     * 나중에 조회한 정보를 덧씌운 새 레지스트리.
     */
    public function merge(self $other): self
    {
        return new self(array_merge($this->apis, $other->apis));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->apis;
    }
}
