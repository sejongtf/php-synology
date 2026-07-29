<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Chat\Api;

use Sejongtf\Synology\Api;

class Misc extends Api
{
    const API_NAME = 'SYNO.Chat.Misc';

    protected array $methods = [
        'acl' => 1,
        'ntp' => 1,
    ];
}
