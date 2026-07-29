<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Contacts\Api;

use Sejongtf\Synology\Api;

/**
 * @method array list(array $params)
 * @method array create(array $params)
 * @method array set(array $params)
 * @method array delete(array $params)
 */
class Label extends Api
{
    const API_NAME = 'SYNO.Contacts.Label';

    protected array $methods = [
        'list' => 2,
        'create' => 2,
        'set' => 2,
        'delete' => 2,
        'add_member' => 2,
        'remove_member' => 2,
        'export' => 2,
    ];
}
