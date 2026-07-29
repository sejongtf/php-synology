<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Personal\Api\Notification;

use Sejongtf\Synology\Api;

class Conf extends Api
{
    const API_NAME = 'SYNO.Personal.Notification.Conf';

    /** @var array<string, int> */
    protected array $methods = [
        'get' => 2,
        'set' => 2,
    ];
}
