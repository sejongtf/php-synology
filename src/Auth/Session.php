<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Auth;

use InvalidArgumentException;

/**
 * DSM 세션. 불변이다.
 *
 * `sid` 만 있으면 충분하다 — 이 패키지는 sid 의 출처를 묻지 않는다.
 * 로그인으로 얻었든, 외부 저장소에서 읽어왔든 똑같이 쓴다.
 *
 * `synoToken` 은 서버가 CSRF 보호를 켰을 때만 생긴다. 있으면 이후 모든 요청에
 * `SynoToken` 파라미터로 붙여야 한다.
 * `did` 는 2단계 인증을 생략하기 위한 기기 식별자다(`enable_device_token=yes` 로 받는다).
 */
final class Session
{
    /**
     * @throws InvalidArgumentException sid 가 비어 있을 때
     */
    public function __construct(
        public readonly string $sid,
        public readonly ?string $synoToken = null,
        public readonly ?string $did = null,
    ) {
        // 빈 sid 세션은 만들어지는 순간부터 쓸모가 없는데 증상이 아무것도 안 나온다.
        // Api::raw() 는 빈 sid 를 falsy 로 보고 _sid 를 아예 빼 버리고, 서버는 119 를
        // 주고, Http\Connection 의 재인증은 _sid 가 실려 있어야 걸리므로 그마저 건너뛴다.
        // 소비자에게는 단서 없는 null 만 돌아가고 저장소에는 ''가 남아 다음 요청도 똑같다.
        // 그래서 세션을 **만드는 자리**에서 막는다 — 로그인 응답 이상, 외부 저장소가
        // 돌려준 빈 값, withSession($url, '') 세 경로가 여기 한 곳으로 모인다.
        if (trim($sid) === '') {
            throw new InvalidArgumentException(
                'DSM 세션의 sid 가 비어 있습니다. 로그인 응답에 sid 가 없었거나 저장소가 빈 값을 돌려줬습니다.',
            );
        }
    }

    /**
     * 로그인 응답의 `data` 에서 만든다.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['sid'] ?? ''),
            isset($data['synotoken']) ? (string) $data['synotoken'] : null,
            isset($data['did']) ? (string) $data['did'] : null,
        );
    }

    public function hasSynoToken(): bool
    {
        return $this->synoToken !== null && $this->synoToken !== '';
    }

    /**
     * 저장소에 넣을 형태. `fromArray()` 로 그대로 되돌릴 수 있다.
     */
    public function toArray(): array
    {
        return array_filter([
            'sid' => $this->sid,
            'synotoken' => $this->synoToken,
            'did' => $this->did,
        ], static fn ($value) => $value !== null);
    }
}
