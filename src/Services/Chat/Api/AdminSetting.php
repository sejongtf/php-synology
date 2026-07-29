<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Chat\Api;

use Sejongtf\Synology\Api;

class AdminSetting extends Api
{
    const API_NAME = 'SYNO.Chat.Admin.Setting';

    protected array $methods = [
        'get' => 3,
    ];
}
