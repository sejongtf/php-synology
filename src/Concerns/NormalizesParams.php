<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Concerns;

/**
 * DSM 이 기대하는 모양으로 파라미터 값을 다듬는 공통 로직.
 *
 * 같은 코드가 Api 클래스 곳곳에 복붙돼 있었다 — 배열 감싸기 7곳, boolean 캐스팅 8곳.
 */
trait NormalizesParams
{
    /**
     * 값 하나든 배열이든 JSON 리스트로 만든다.
     *
     * `array_values` 로 키를 버리는 게 중요하다. 키가 남으면 `json_encode` 가
     * `[]` 가 아니라 `{}` 를 만들고 DSM 이 거부한다.
     */
    protected static function asList(mixed $value): array
    {
        return array_values(is_array($value) ? $value : [$value]);
    }

    /**
     * DSM 은 boolean 을 `'true'`/`'false'` **문자열**로 받는다.
     *
     * PHP bool 을 그냥 쿼리에 실으면 `1`/`0` 이 되어 조용히 잘못 읽힌다.
     */
    protected static function asBool(bool $value): string
    {
        return $value ? 'true' : 'false';
    }
}
