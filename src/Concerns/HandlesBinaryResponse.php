<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Concerns;

use Sejongtf\Synology\Message\Response;

/**
 * 파일이 내려오는 엔드포인트의 오류 판정.
 *
 * 판정을 두 가지로 해야 한다:
 *
 * 1. **JSON/plaintext 로 왔으면** 파일이 아니라 오류 봉투다.
 * 2. **HTTP 상태가 실패면** 본문 타입과 무관하게 실패다.
 *    `SYNO.FileStation.Download` 를 `mode=open` 으로 부르면 실패 시 JSON 이 아니라
 *    HTTP 404 가 온다 — 1번만 보면 이걸 파일로 착각한다.
 */
trait HandlesBinaryResponse
{
    /**
     * 요청을 보내고, 오류면 예외로 올리고, 아니면 Response 를 그대로 돌려준다.
     *
     * 본문을 문자열로 버퍼링하지 않는다. 큰 파일은 `toPsrResponse()->getBody()` 로
     * 스트림째 다뤄야 한다.
     *
     * @param  array<string, mixed>  $params
     * @return Response|null 연결이 없으면 null
     */
    protected function binary(string $method, ?int $version = null, array $params = []): ?Response
    {
        $response = $this->raw($method, $version, $params);

        if ($response === null) {
            return null;
        }

        if (! $response->success() || $response->isJsonOrPlaintext()) {
            $response->throw();
        }

        return $response;
    }
}
