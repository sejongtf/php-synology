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

    /**
     * 아직 쓰지 않은 OTP 코드.
     *
     * 로그인이 한 번 통과하면 버린다. TOTP 코드는 일회용이라, 세션이 만료돼 다시
     * 로그인할 때 같은 코드를 또 보내면 404(Failed to authenticate 2-factor code)를
     * 맞는다. 코드를 지워 두면 대신 403(2-factor code required)이 오고, 그게 소비자가
     * `AuthException::requiresOtp()` 로 새 코드를 받아 재시도할 수 있는 신호다.
     */
    private ?string $otpCode;

    /**
     * 마지막 로그인이 받아 온 device token(`did`).
     *
     * 만료되는 건 세션이지 기기 등록이 아니다. 이 값을 다음 로그인에 `device_id` 로
     * 넘기면 2단계 인증이 강제된 계정에서도 OTP 없이 재로그인할 수 있다.
     */
    private ?string $deviceId;

    public function __construct(
        Connection $connection,
        private readonly SessionStore $sessions,
        private readonly Credentials $credentials,
    ) {
        $this->api = new Auth($connection);
        $this->otpCode = $credentials->otpCode;
        $this->deviceId = $credentials->deviceId;
    }

    /**
     * 로그인해서 세션을 저장소에 넣고 돌려준다.
     *
     * @param  string|null  $otpCode  이번 로그인에만 쓸 2단계 인증 코드. 403/406 을 잡고
     *                                사용자에게 코드를 받아 재시도하는 흐름을 위한 것이다.
     *                                생략하면 아직 쓰지 않은 코드가 있을 때 그걸 쓴다.
     *
     * @throws AuthException 자격증명이 틀렸거나 OTP 가 필요한 경우
     * @throws \InvalidArgumentException DSM 이 성공이라면서 sid 를 안 준 경우.
     *                                   빈 세션을 저장소에 넣으면 이후 모든 요청이
     *                                   단서 없이 실패하므로 여기서 끊는다.
     */
    public function login(?string $otpCode = null): Session
    {
        $data = $this->api->login(
            account: $this->credentials->account,
            passwd: $this->credentials->passwd,
            session: $this->credentials->session,
            otpCode: $otpCode ?? $this->otpCode,
            deviceId: $this->deviceId,
            deviceName: $this->credentials->deviceName,
            enableDeviceToken: $this->credentials->rememberDevice,
        );

        $session = Session::fromArray($data ?? []);

        // 여기까지 왔으면 로그인이 통과한 것이다(실패는 Auth::login() 이 예외로 올린다).
        // 코드는 소모됐고, 새 did 를 받았으면 그걸 들고 간다.
        $this->otpCode = null;
        $this->deviceId = $session->did ?? $this->deviceId;

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
        // 버리기 전에 did 를 챙긴다. 다른 프로세스가 로그인해 저장소에 넣어 둔 세션이면
        // 이 인스턴스는 did 를 본 적이 없고, 여기서 놓치면 재로그인이 OTP 를 요구한다.
        $this->deviceId ??= $this->sessions->get()?->did;

        $this->sessions->forget();

        return $this->login();
    }

    public function logout(): void
    {
        $this->api->logout($this->credentials->session);

        $this->sessions->forget();
    }
}
