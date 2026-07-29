<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Personal\Api\Notification;

use Sejongtf\Synology\Api;

class Mobile extends Api
{
    const API_NAME = 'SYNO.Personal.Notification.Mobile';

    /** @var array<string, int> */
    protected array $methods = [
        'stat' => 3,
        'pair' => 3,
        'unpair' => 3,
    ];
}
