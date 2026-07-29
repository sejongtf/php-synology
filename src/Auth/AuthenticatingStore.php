<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Auth;

use Closure;
use Sejongtf\Synology\Contracts\SessionStore;

/**
 * 세션이 비어 있으면 그때 로그인하는 저장소 데코레이터.
 *
 * `Synology::connect()` 가 생성 시점에 네트워크를 때리지 않게 해 준다.
 * (서비스 컨테이너 부팅 중에 로그인이 일어나면 곤란하다.)
 *
 * 재귀는 생기지 않는다. `SYNO.API.Auth` 는 `$auth = false` 라서 로그인 요청 자체는
 * `_sid` 를 필요로 하지 않고, 따라서 이 저장소를 다시 부르지 않는다.
 * `Authenticator` 도 데코레이터가 아니라 안쪽 저장소를 직접 들고 있다.
 */
final class AuthenticatingStore implements SessionStore
{
    /**
     * @param  Closure():Session  $login
     */
    public function __construct(
        private readonly SessionStore $inner,
        private readonly Closure $login,
    ) {}

    public function get(): ?Session
    {
        return $this->inner->get() ?? ($this->login)();
    }

    public function put(Session $session): void
    {
        $this->inner->put($session);
    }

    public function forget(): void
    {
        $this->inner->forget();
    }
}
