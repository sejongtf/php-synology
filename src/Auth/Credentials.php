<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Auth;

/**
 * 로그인에 필요한 값 묶음. 불변이다.
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

    /**
     * OTP 코드만 바꾼 새 객체. 403(2단계 인증 필요)을 받고 다시 시도할 때 쓴다.
     */
    public function withOtpCode(string $otpCode): self
    {
        return new self(
            $this->account,
            $this->passwd,
            $this->session,
            $otpCode,
            $this->deviceId,
            $this->deviceName,
            $this->rememberDevice,
        );
    }
}
