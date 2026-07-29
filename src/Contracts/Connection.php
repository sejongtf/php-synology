<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Contracts;

use Sejongtf\Synology\Message\Response;

/**
 * DSM 에 요청을 내보내는 계층. 소비자가 자기 구현으로 갈아끼울 수 있는 지점이다.
 *
 * **PSR-18 클라이언트와 다른 물건이다.** PSR-18 은 HTTP 를 한 번 주고받는 아래층이고,
 * 이건 그 위에서 DSM 프로토콜(`api`/`version`/`method`, 세션, 엔드포인트)을 아는 층이다.
 * 예전 이름이 `Contracts\Client` 여서 둘이 같은 것처럼 읽혔다 — 소비자가 PSR-18 구현체를
 * 넘기면서 동시에 이 인터페이스를 구현하는 일이 생기는 자리라 이름을 갈랐다.
 * 기본 구현은 `Http\Connection` 이고, 그게 PSR-18 구현체를 받아 쓴다.
 *
 * 응답 타입은 구체 클래스 `Message\Response` 로 고정한다. 봉투를 읽는 규칙
 * (`success` 판정, `{}` 와 `[]` 구분, 바이너리 본문을 버퍼링하지 않는 것)은
 * DSM 프로토콜 그 자체라 구현마다 달라질 여지가 없고, PSR-7 응답 하나만 있으면
 * 만들 수 있어서 교체 구현에 부담이 되지 않는다.
 */
interface Connection
{
    /**
     * DSM 에 요청 하나를 보낸다.
     *
     * `$params` 의 값은 이미 문자열로 다듬어져 온다 — 배열을 JSON 으로 바꾸는 것도,
     * bool 을 `'true'`/`'false'` 로 바꾸는 것도 `Api::raw()` 가 끝내고 넘긴다.
     * 구현이 다시 손댈 필요는 없다.
     *
     * @param  array<string, mixed>  $params
     */
    public function request(string $api, int $version, string $method, array $params = []): Response;

    /**
     * 지금 세션의 sid. 없으면 null.
     *
     * `_sid` 를 실제로 붙이는 건 `Api::raw()` 다 — `$auth = false` 인 API 는 세션이
     * 있어도 붙이면 안 되기 때문에, 이 메서드는 알려 주기만 한다.
     */
    public function getSessionId(): ?string;

    public function getEndpoint(): string;
}
