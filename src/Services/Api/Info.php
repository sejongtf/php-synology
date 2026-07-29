<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Api;

use Sejongtf\Synology\Api;

/**
 * 사용 가능한 API 목록과 각 API 의 경로·버전을 조회한다.
 *
 * 이 API 의 위치만은 고정이라 언제나 `/webapi/entry.cgi` 로 부를 수 있다.
 * 나머지 API 는 여기서 받은 `path` 를 써야 한다.
 *
 * 로그인 전에도 호출할 수 있어야 하므로 `_sid` 를 붙이지 않는다(레지스트리의
 * `authLevel: 0` 과 같은 뜻이다). 버전은 min·max 가 둘 다 1 이라 협상할 것이 없다.
 *
 * @method mixed query(array $params = [])
 */
class Info extends Api
{
    const API_NAME = 'SYNO.API.Info';

    // authLevel 0 — 세션을 쓰지 않는다.
    const AUTH = false;

    /** @var array<string, int> */
    protected array $methods = [
        'query' => 1,
    ];

    /**
     * @param  string|array|null  $query  조회할 API 이름. 생략하면 전체.
     */
    public function query($query = null)
    {
        $params = [];

        if ($query !== null) {
            // DSM 은 쉼표로 구분된 문자열을 받는다. 접두사만 줘도 하위 API 가 함께 나온다.
            $params['query'] = is_array($query) ? implode(',', $query) : $query;
        }

        return $this->request(__FUNCTION__, 1, $params);
    }
}
