<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Personal\Api\Application;

use Sejongtf\Synology\Api;

class Info extends Api
{
    const API_NAME = 'SYNO.Personal.Application.Info';

    /** @var array<string, int> */
    protected array $methods = [
        'get' => 1,
        'setorder' => 1,
        'get_autotest_log' => 1,
    ];
}
