<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Personal\Api\Notification;

use Sejongtf\Synology\Api;

class Event extends Api
{
    const API_NAME = 'SYNO.Personal.Notification.Event';

    /** @var array<string, int> */
    protected array $methods = [
        'get' => 1,
        'delete' => 2,
        'list' => 2,
        'send' => 1,
    ];
}
