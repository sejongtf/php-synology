<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Auth;

use Sejongtf\Synology\Contracts\Connection;
use Sejongtf\Synology\Contracts\SessionStore;
use Sejongtf\Synology\Exceptions\AuthException;
use Sejongtf\Synology\Services\Api\Auth;

/**
 * 자격증명으로 세션을 얻어 저장소에 넣는다.
 *
 * **선택 경로다.** sid 를 외부에서 관리한다면 이 클래스는 필요 없다 —
 * `Synology::withSession()` 이나 `withStore()` 로 충분하다.
 */
final class Authenticator
{
    private readonly Auth $api;

    public function __construct(
        Connection $connection,
        private readonly SessionStore $sessions,
        private readonly Credentials $credentials,
    ) {
        $this->api = new Auth($connection);
    }

    /**
     * 로그인해서 세션을 저장소에 넣고 돌려준다.
     *
     * @throws AuthException 자격증명이 틀렸거나 OTP 가 필요한 경우
     * @throws \InvalidArgumentException DSM 이 성공이라면서 sid 를 안 준 경우.
     *                                   빈 세션을 저장소에 넣으면 이후 모든 요청이
     *                                   단서 없이 실패하므로 여기서 끊는다.
     */
    public function login(): Session
    {
        $data = $this->api->login(
            account: $this->credentials->account,
            passwd: $this->credentials->passwd,
            session: $this->credentials->session,
            otpCode: $this->credentials->otpCode,
            deviceId: $this->credentials->deviceId,
            deviceName: $this->credentials->deviceName,
            enableDeviceToken: $this->credentials->rememberDevice,
        );

        $session = Session::fromArray($data ?? []);

        $this->sessions->put($session);

        return $session;
    }

    /**
     * 저장소에 세션이 없을 때만 로그인한다.
     */
    public function session(): Session
    {
        return $this->sessions->get() ?? $this->login();
    }

    /**
     * 세션을 버리고 다시 로그인한다. 106/107/119 를 만났을 때 쓴다.
     */
    public function refresh(): Session
    {
        $this->sessions->forget();

        return $this->login();
    }

    public function logout(): void
    {
        $this->api->logout($this->credentials->session);

        $this->sessions->forget();
    }
}
