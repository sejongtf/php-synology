<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Personal\Api\Notification;

use Sejongtf\Synology\Api;

class Filter extends Api
{
    const API_NAME = 'SYNO.Personal.Notification.Filter';

    /** @var array<string, int> */
    protected array $methods = [
        'set' => 1,
        'list' => 1,
    ];
}
