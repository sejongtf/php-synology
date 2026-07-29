<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Personal\Api\MailAccount;

use Sejongtf\Synology\Api;

class Contacts extends Api
{
    const API_NAME = 'SYNO.Personal.MailAccount.Contacts';

    /** @var array<string, int> */
    protected array $methods = [
        'list' => 1,
    ];
}
