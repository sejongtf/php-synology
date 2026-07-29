<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Core\Api;

use Sejongtf\Synology\Api;

/**
 * @method array list(array $params)
 * @method array get(array $params)
 */
class User extends Api
{
    const API_NAME = 'SYNO.Core.User';

    protected array $methods = [
        'list' => 1, // 파라미터 - type(필수): local, additional: [''](email, uid, email, description, ...)
        'get' => 1, // 파라미터 - name(필수): username, additional: ['']...
        // 'set' => 1, // set은 api에서 설정불가능한 듯?
    ];
}
