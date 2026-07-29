<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Personal\Api\Notification;

use Sejongtf\Synology\Api;

class Identifier extends Api
{
    const API_NAME = 'SYNO.Personal.Notification.Identifier';

    /** @var array<string, int> */
    protected array $methods = [
        'get' => 1,
    ];
}
