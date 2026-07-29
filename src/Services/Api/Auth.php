<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Api;

use Sejongtf\Synology\Api;

/**
 * 로그인/로그아웃.
 *
 * 버전 3–7 이 있고 문서는 **6 을 권장**한다. 6 부터 SynoToken 과 device token 이 생겼다.
 *
 * **maxVersion 으로 올리면 안 된다.** 레지스트리를 보면 7 은 `token`/`session`/
 * `synotoken`/`oidc` 를 빼고 `suspend`/`resume` 를 넣었다 — 여기서 쓰는 `token` 이
 * 사라진다. DSM API 에서 메서드가 상위 버전에서 없어지는 드문 사례다.
 *
 * 로그인 요청 자체에는 세션이 없으므로 `_sid` 를 붙이지 않는다(레지스트리의
 * `authLevel: 0` 과 같은 뜻이다).
 *
 * @link  https://global.download.synology.com/download/Document/Software/DeveloperGuide/Os/DSM/All/enu/DSM_Login_Web_API_Guide_enu.pdf
 *
 * @method mixed token(array $params = [])
 */
class Auth extends Api
{
    const API_NAME = 'SYNO.API.Auth';

    // authLevel 0 — 세션을 쓰지 않는다.
    const AUTH = false;

    /** @var array<string, int> */
    protected array $methods = [
        'login' => 6,
        'logout' => 6,
        'token' => 6,
    ];

    /**
     * @param  string|null  $session  DSM 애플리케이션별 세션 이름 (예: FileStation)
     * @param  string|null  $otpCode  2단계 인증 코드
     * @param  string|null  $deviceId 이전에 받아 둔 did. 있으면 OTP 를 생략한다
     * @param  string|null  $deviceName  OTP 면제를 등록/식별할 기기 이름
     * @param  bool  $enableDeviceToken  이번 로그인으로 did 를 발급받을지
     */
    public function login(
        string $account,
        string $passwd,
        ?string $session = null,
        ?string $otpCode = null,
        ?string $deviceId = null,
        ?string $deviceName = null,
        bool $enableDeviceToken = false,
        bool $enableSynoToken = true,
    ) {
        $params = [
            'account' => $account,
            'passwd' => $passwd,
            // sid 를 응답 JSON 으로 받는다. 쿠키에 의존하지 않기 위해서다.
            'format' => 'sid',
        ];

        if ($enableSynoToken) {
            $params['enable_syno_token'] = 'yes';
        }

        if ($session !== null) {
            $params['session'] = $session;
        }

        if ($otpCode !== null) {
            $params['otp_code'] = $otpCode;
        }

        if ($enableDeviceToken) {
            $params['enable_device_token'] = 'yes';
        }

        if ($deviceName !== null) {
            $params['device_name'] = $deviceName;
        }

        if ($deviceId !== null) {
            $params['device_id'] = $deviceId;
        }

        return $this->raw(__FUNCTION__, $this->methods[__FUNCTION__], $params)?->throw()->data();
    }

    /**
     * @param  string|null  $session  로그인할 때 준 세션 이름이 있으면 같이 준다.
     */
    public function logout(?string $session = null)
    {
        $params = [];

        if ($session !== null) {
            $params['session'] = $session;
        }

        // 로그아웃은 세션을 지목해야 하므로 _sid 가 필요하다.
        if ($sid = $this->getConnection()?->getSessionId()) {
            $params['_sid'] = $sid;
        }

        return $this->request(__FUNCTION__, $this->methods[__FUNCTION__], $params);
    }
}
