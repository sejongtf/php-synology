<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Exceptions;

use Psr\Http\Message\ResponseInterface;
use Throwable;

class ApiException extends RequestException
{
    const COMMON_ERROR_CODES = [
        100 => 'Unknown error',
        101 => 'No parameter of API, method or version',
        102 => 'The requested API does not exist',
        103 => 'The requested method does not exist',
        104 => 'The requested version does not support the functionality',
        105 => 'The logged in session does not have permission',
        106 => 'Session timeout',
        107 => 'Session interrupted by duplicated login',
        108 => 'Failed to upload the file',
        109 => 'The network connection is unstable or the system is busy',
        110 => 'The network connection is unstable or the system is busy',
        111 => 'The network connection is unstable or the system is busy',
        112 => 'Preserve for other purpose',
        113 => 'Preserve for other purpose',
        114 => 'Lost parameters for this API',
        115 => 'Not allowed to upload a file',
        116 => 'Not allowed to perform for a demo site',
        117 => 'The network connection is unstable or the system is busy',
        118 => 'The network connection is unstable or the system is busy',
        119 => 'Invalid session / SID not found.',
        // 120-149 Preserve for other purpose
        120 => 'Preserve for other purpose',
        121 => 'Preserve for other purpose',
        122 => 'Preserve for other purpose',
        123 => 'Preserve for other purpose',
        124 => 'Preserve for other purpose',
        125 => 'Preserve for other purpose',
        126 => 'Preserve for other purpose',
        127 => 'Preserve for other purpose',
        128 => 'Preserve for other purpose',
        129 => 'Preserve for other purpose',
        130 => 'Preserve for other purpose',
        131 => 'Preserve for other purpose',
        132 => 'Preserve for other purpose',
        133 => 'Preserve for other purpose',
        134 => 'Preserve for other purpose',
        135 => 'Preserve for other purpose',
        136 => 'Preserve for other purpose',
        137 => 'Preserve for other purpose',
        138 => 'Preserve for other purpose',
        139 => 'Preserve for other purpose',
        140 => 'Preserve for other purpose',
        141 => 'Preserve for other purpose',
        142 => 'Preserve for other purpose',
        143 => 'Preserve for other purpose',
        144 => 'Preserve for other purpose',
        145 => 'Preserve for other purpose',
        146 => 'Preserve for other purpose',
        147 => 'Preserve for other purpose',
        148 => 'Preserve for other purpose',
        149 => 'Preserve for other purpose',
        150 => 'Request source IP does not match the login IP',
        160 => 'Insufficient application privilege',
    ];

    /**
     * 서비스별 코드 테이블. 하위 클래스가 덮어쓴다.
     *
     * 공통 테이블보다 먼저 조회된다. 같은 숫자가 문맥에 따라 다른 뜻을 갖기 때문이다 —
     * 400 은 인증에서는 "계정 없음/비밀번호 오류"고 파일 연산에서는 "파라미터 오류"다.
     * 그래서 두 테이블은 합칠 수 없고 예외 클래스로 갈라야 한다.
     *
     * @var array<int, string>
     */
    const ERROR_CODES = [];

    private int $errorCode = 0;

    public function __construct(ResponseInterface $response, int $errorCode, string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        $this->errorCode = $errorCode;

        if (! $message) {
            $message = 'Error Code '.$errorCode.': '.static::describe($errorCode);
        }

        parent::__construct($response, $message, $code, $previous);
    }

    /**
     * 코드에 해당하는 설명. 서비스 테이블 → 공통 테이블 순으로 찾는다.
     */
    public static function describe(int $errorCode): string
    {
        return static::ERROR_CODES[$errorCode]
            ?? static::COMMON_ERROR_CODES[$errorCode]
            ?? 'Unknown error code: '.$errorCode;
    }

    public function getErrorCode(): int
    {
        return $this->errorCode;
    }
}
