<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Core\Api;

use Sejongtf\Synology\Api;

/**
 * @method array list(array $params)
 * @method array get(array $params)
 */
class Group extends Api
{
    const API_NAME = 'SYNO.Core.Group';

    protected array $methods = [
        'list' => 1, // type(필수): local, name_only: false, offset, limit...
        'get' => 1,
        'set' => 1,
    ];
}
