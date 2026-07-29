<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Personal\Api\Notification;

use Sejongtf\Synology\Api;

class GDPR extends Api
{
    const API_NAME = 'SYNO.Personal.Notification.GDPR';

    /** @var array<string, int> */
    protected array $methods = [
        'set' => 1,
        'get' => 1,
    ];
}
