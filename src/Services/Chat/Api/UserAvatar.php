<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Chat\Api;

use Sejongtf\Synology\Api;

/**
 * @method mixed get(array $params = [])
 */
class UserAvatar extends Api
{
    const API_NAME = 'SYNO.Chat.User.Avatar';

    /** @var array<string, int> */
    protected array $methods = [
        'get' => 1,
    ];
}
