<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Auth;

/**
 * 로그인에 필요한 값 묶음. 불변이다.
 *
 * `otpCode` 는 **첫 로그인에서 한 번만** 쓰인다. TOTP 코드는 일회용이라
 * `Authenticator` 가 성공한 뒤 버리고, 이후 재로그인에는 싣지 않는다.
 * 새 코드로 다시 시도하려면 `Authenticator::login($code)` 로 넘긴다.
 */
final class Credentials
{
    public function __construct(
        public readonly string $account,
        public readonly string $passwd,
        public readonly ?string $session = null,
        public readonly ?string $otpCode = null,
        public readonly ?string $deviceId = null,
        public readonly ?string $deviceName = null,
        public readonly bool $rememberDevice = false,
    ) {}
}
