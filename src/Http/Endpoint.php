<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Http;

use InvalidArgumentException;

/**
 * NAS 주소와 `/webapi/<path>` 조립을 담당한다.
 */
final class Endpoint
{
    private readonly string $base;

    /**
     * @param  string  $baseUrl  예: `https://nas.example.com:5001`
     *                           `/webapi` 까지 붙여 줘도 받아들인다.
     */
    public function __construct(string $baseUrl)
    {
        $base = rtrim(trim($baseUrl), '/');

        if ($base === '' || parse_url($base, PHP_URL_SCHEME) === null) {
            throw new InvalidArgumentException("NAS 주소는 scheme 을 포함한 절대 URL 이어야 합니다: [{$baseUrl}]");
        }

        // 소비자가 이미 /webapi 까지 준 경우를 흡수한다.
        if (str_ends_with($base, '/webapi')) {
            $base = substr($base, 0, -strlen('/webapi'));
        }

        $this->base = $base;
    }

    public function base(): string
    {
        return $this->base;
    }

    /**
     * @param  string  $path  `SYNO.API.Info` 가 알려 준 경로. 보통 `entry.cgi`.
     */
    public function url(string $path): string
    {
        return $this->base.'/webapi/'.ltrim($path, '/');
    }
}
