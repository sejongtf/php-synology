<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Http;

use Closure;
use Psr\Http\Message\ResponseInterface as PsrResponse;
use Sejongtf\Synology\Exceptions\ApiException;
use Sejongtf\Synology\Exceptions\AuthException;
use Sejongtf\Synology\Services\Calendar\CalendarException;

/**
 * 어느 API 를 불렀는지에 따라 예외 클래스를 고른다.
 *
 * 이게 필요한 이유는 코드 표가 겹치기 때문이다. 400 하나만 봐도
 * `SYNO.API.Auth` 에서는 "계정 없음/비밀번호 오류", 파일 연산에서는
 * "파라미터 오류"다. API 이름을 모르면 어느 표를 봐야 할지 알 수 없다.
 */
final class ErrorMapper
{
    /**
     * 실패 응답을 예외로 바꾸는 클로저. `Message\Response::throw()` 가 호출한다.
     */
    /**
     * 세션이 끝났다는 뜻의 공통 코드. 어느 API 에서 나오든 인증 문제다.
     *
     * 106 = Session timeout, 107 = 중복 로그인으로 세션 끊김, 119 = SID not found.
     */
    private const SESSION_EXPIRED_CODES = [106, 107, 119];

    public static function for(?string $api): Closure
    {
        $default = self::exceptionClass($api);

        return static function (PsrResponse $psr, array $error) use ($default): ApiException {
            $code = (int) ($error['code'] ?? 0);

            // 106/107/119 는 어느 서비스에서 나오든 파일/일정 오류가 아니라 세션 문제다.
            // 소비자가 catch (AuthException) 하나로 세션 갱신을 처리할 수 있어야 한다.
            $class = in_array($code, self::SESSION_EXPIRED_CODES, true)
                ? AuthException::class
                : $default;

            return new $class($psr, $code, self::describe($class, $error));
        };
    }

    /**
     * @return class-string<ApiException>
     */
    public static function exceptionClass(?string $api): string
    {
        return match (true) {
            $api === null => ApiException::class,
            $api === 'SYNO.API.Auth' => AuthException::class,
            str_starts_with($api, 'SYNO.Cal.') => CalendarException::class,
            default => ApiException::class,
        };
    }

    /**
     * 상세 오류가 있으면 메시지에 함께 담는다.
     *
     * 바깥 코드와 `errors[].code` 는 서로 다른 표라서, 바깥 것만 보여주면
     * 정작 원인을 알 수 없다. 예를 들어 `{"code":1100,"errors":[{"code":408,"path":"/test/:"}]}`
     * 에서 실제 원인은 408(No such file or directory)이고 경로까지 들어 있다.
     *
     * @param  class-string<ApiException>  $class
     * @param  array{code: int, errors?: array<int, array<string, mixed>>}  $error
     */
    private static function describe(string $class, array $error): string
    {
        $details = $error['errors'] ?? null;

        if (! $details) {
            return '';  // 빈 문자열이면 예외가 자기 표에서 메시지를 만든다.
        }

        $code = (int) ($error['code'] ?? 0);

        return sprintf(
            'Error Code %d: %s (%s)',
            $code,
            $class::describe($code),
            json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );
    }
}
