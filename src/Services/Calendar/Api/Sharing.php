<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Calendar\Api;

use Sejongtf\Synology\Api;

/**
 * @method array get(array $params)
 * @method array set(array $params)
 */
class Sharing extends Api
{
    const API_NAME = 'SYNO.Cal.Sharing';

    protected array $methods = [
        'get' => 1,
        'set' => 1,
    ];
}
