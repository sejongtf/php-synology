<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Chat\Api;

use Sejongtf\Synology\Api;

/**
 * @method array list(array $params)
 * @method array get(array $params)
 */
class User extends Api
{
    const API_NAME = 'SYNO.Chat.User';

    protected array $methods = [
        'notify_encrypt' => 2,
        'change_password' => 2,
        'update_key' => 2,
        'notification_badge' => 2,
        'login' => 2,
        'polling' => 1,
        'list' => 2,
        'set' => 1,
        'get' => 1,
        'privilege_user_count' => 2,
    ];
}
