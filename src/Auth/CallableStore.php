<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Auth;

use Closure;
use Sejongtf\Synology\Contracts\SessionStore;

/**
 * 외부 저장소에 클로저로 위임하는 저장소.
 *
 * ```php
 * new CallableStore(
 *     get: fn () => Cache::get('syno.session'),
 *     put: fn (Session $s) => Cache::put('syno.session', $s, 3600),
 *     forget: fn () => Cache::forget('syno.session'),
 * );
 * ```
 *
 * `get` 은 `Session`, 배열(`Session::fromArray()` 로 복원), 또는 sid 문자열을 돌려줄 수 있다.
 * 캐시에 객체를 직렬화해 넣기 싫은 경우가 흔해서 배열과 문자열도 받는다.
 */
final class CallableStore implements SessionStore
{
    public function __construct(
        private readonly Closure $get,
        private readonly ?Closure $put = null,
        private readonly ?Closure $forget = null,
    ) {}

    public function get(): ?Session
    {
        return self::toSession(($this->get)());
    }

    public function put(Session $session): void
    {
        if ($this->put) {
            ($this->put)($session);
        }
    }

    public function forget(): void
    {
        if ($this->forget) {
            ($this->forget)();
        }
    }

    private static function toSession(mixed $value): ?Session
    {
        if ($value instanceof Session) {
            return $value;
        }

        if (is_array($value)) {
            return $value === [] ? null : Session::fromArray($value);
        }

        if (is_string($value) && $value !== '') {
            return new Session($value);
        }

        return null;
    }
}
