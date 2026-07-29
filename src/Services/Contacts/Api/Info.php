<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Contacts\Api;

use Sejongtf\Synology\Api;

/**
 * @method array get_timezone()
 */
class Info extends Api
{
    const API_NAME = 'SYNO.Contacts.Info';

    protected array $methods = [
        'get_timezone' => 1,
    ];
}
