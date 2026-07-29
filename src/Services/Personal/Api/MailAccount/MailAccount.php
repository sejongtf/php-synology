<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Personal\Api\MailAccount;

use Sejongtf\Synology\Api;

class MailAccount extends Api
{
    const API_NAME = 'SYNO.Personal.MailAccount';

    /** @var array<string, int> */
    protected array $methods = [
        'set' => 1,
        'delete' => 1,
        'get' => 1,
        'update' => 1,
        'test' => 1,
    ];
}
