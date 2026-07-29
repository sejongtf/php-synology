<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Chat\Api;

use Sejongtf\Synology\Api;

class ChannelHashtag extends Api
{
    const API_NAME = 'SYNO.Chat.Channel.Hashtag';

    protected array $methods = [
        'list' => 1,
    ];
}
