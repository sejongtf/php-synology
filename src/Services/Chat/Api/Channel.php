<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Chat\Api;

use Sejongtf\Synology\Api;

class Channel extends Api
{
    const API_NAME = 'SYNO.Chat.Channel';

    protected array $methods = [
        'list' => 5,
        'enter' => 2,
    ];
}
