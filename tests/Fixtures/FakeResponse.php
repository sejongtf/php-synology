<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Tests\Fixtures;

use Nyholm\Psr7\Response as PsrResponse;
use Sejongtf\Synology\Message\Response;

/**
 * 진짜 `Message\Response` 를 만들어 주는 팩토리.
 *
 * 예전에는 `Contracts\Response` 를 따로 구현한 124줄짜리 가짜였다. 그 계약이 사라지면서
 * 흉내 낼 이유도 없어졌고, 무엇보다 **가짜가 진짜와 다르게 판단할 위험**이 사라졌다 —
 * 이제 테스트는 실제 봉투 해석 코드를 그대로 지나간다.
 */
final class FakeResponse
{
    /**
     * 성공 봉투. `$data` 가 null 이면 `data` 키 자체를 넣지 않는다
     * (성공+빈 데이터와 data 누락을 구분하기 위해).
     */
    public static function json(mixed $data = []): Response
    {
        $payload = ['success' => true];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return self::of(200, 'application/json', (string) json_encode($payload));
    }

    /**
     * 실패 봉투.
     *
     * @param  array<string, mixed>  $error  예: `['code' => 408]`
     */
    public static function error(array $error): Response
    {
        return self::of(200, 'application/json', (string) json_encode([
            'success' => false,
            'error' => $error,
        ]));
    }

    public static function binary(string $body = 'BINARY'): Response
    {
        return self::of(200, 'application/octet-stream', $body);
    }

    /**
     * 바이너리를 기대했는데 HTTP 오류가 온 경우.
     *
     * `SYNO.FileStation.Download` 를 `mode=open` 으로 부르면 실패 시 JSON 봉투가
     * 아니라 HTTP 404 가 온다.
     */
    public static function failedBinary(int $status = 404): Response
    {
        return self::of($status, 'text/html', '');
    }

    public static function of(int $status, string $contentType, string $body): Response
    {
        return new Response(new PsrResponse($status, ['Content-Type' => $contentType], $body));
    }
}
