<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Chat\Api;

use Sejongtf\Synology\Api;

class ChannelMember extends Api
{
    const API_NAME = 'SYNO.Chat.Channel.Member';

    protected array $methods = [
        'get' => 1,
    ];
}
