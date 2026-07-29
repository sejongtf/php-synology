<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Contracts;

use Sejongtf\Synology\Auth\Session;

/**
 * 세션을 어디에 두고 읽을지에 대한 계약.
 *
 * 이 패키지는 세션을 자기 안에만 들고 있지 않는다. 소비자가 이미 캐시나 세션 저장소로
 * sid 를 관리하고 있다면 그쪽을 그대로 쓸 수 있어야 한다.
 *
 * PSR-16 을 요구하지 않는 이유: 그러면 캐시 구현을 강제하게 된다.
 * PSR-16 을 쓰는 쪽은 `Auth\Psr16Store` 어댑터를 쓰면 된다.
 */
interface SessionStore
{
    public function get(): ?Session;

    public function put(Session $session): void;

    /**
     * 세션을 버린다. 만료(106)나 SID 없음(119) 을 만났을 때 호출된다.
     */
    public function forget(): void;
}
