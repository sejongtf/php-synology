<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Tests\Fixtures;

use Sejongtf\Synology\Api;

class StubApi extends Api
{
    const API_NAME = 'SYNO.Test.Stub';

    protected array $methods = [
        'list' => 3,
        'get' => 1,
    ];
}

/**
 * `authLevel: 0` 인 API 를 흉내낸다. `AUTH` 는 상수라 인스턴스에서 뒤집을 수 없으므로,
 * 세션을 안 쓰는 쪽을 검사하려면 이렇게 클래스를 따로 둔다.
 */
class UnauthenticatedStubApi extends StubApi
{
    const AUTH = false;
}
