<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Personal\Api\Notification;

use Sejongtf\Synology\Api;

class Device extends Api
{
    const API_NAME = 'SYNO.Personal.Notification.Device';

    /** @var array<string, int> */
    protected array $methods = [
        'unpair' => 1,
        'list' => 2,
    ];
}
