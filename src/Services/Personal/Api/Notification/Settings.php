<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Personal\Api\Notification;

use Sejongtf\Synology\Api;

class Settings extends Api
{
    const API_NAME = 'SYNO.Personal.Notification.Settings';

    /** @var array<string, int> */
    protected array $methods = [
        'setup' => 1,
        'set' => 2,
        'get' => 2,
    ];
}
