<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Auth;

use Sejongtf\Synology\Contracts\SessionStore;

/**
 * 프로세스가 사는 동안만 세션을 들고 있는 기본 저장소.
 *
 * sid 를 직접 주입하는 경우(`Synology::withSession()`)에도 이걸 쓴다.
 */
final class InMemoryStore implements SessionStore
{
    public function __construct(private ?Session $session = null) {}

    public function get(): ?Session
    {
        return $this->session;
    }

    public function put(Session $session): void
    {
        $this->session = $session;
    }

    public function forget(): void
    {
        $this->session = null;
    }
}
