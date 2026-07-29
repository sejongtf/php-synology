<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Exceptions;

/**
 * SYNO.API.Auth 오류 (400–410).
 *
 * DSM Login Web API Guide 의 "API Error Codes" 표다. 405 는 문서에 없다.
 *
 * 주의: 이 숫자들은 `Exceptions\FileOperationException` 의 400–421 과 겹치지만
 * 뜻이 전혀 다르다. 그래서 두 표를 하나로 합칠 수 없고, 어느 API 를 불렀는지에 따라
 * 예외 타입을 골라야 한다.
 */
class AuthException extends ApiException
{
    const ERROR_CODES = [
        400 => 'No such account or incorrect password',
        401 => 'Disabled account',
        402 => 'Denied permission',
        403 => '2-factor authentication code required',
        404 => 'Failed to authenticate 2-factor authentication code',
        406 => 'Enforce to authenticate with 2-factor authentication code',
        407 => 'Blocked IP source',
        408 => 'Expired password cannot change',
        409 => 'Expired password',
        410 => 'Password must be changed',
    ];

    /**
     * 2단계 인증 코드가 필요하다는 뜻인지(코드 입력 UI 를 띄울 시점).
     */
    public function requiresOtp(): bool
    {
        return in_array($this->getErrorCode(), [403, 406], true);
    }

    /**
     * 세션을 버리고 다시 로그인해야 하는 종류인지.
     */
    public function isCredentialFailure(): bool
    {
        return in_array($this->getErrorCode(), [400, 401, 402, 404, 407], true);
    }
}
