<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Personal\Api\MailAccount;

use Sejongtf\Synology\Api;

class Mail extends Api
{
    const API_NAME = 'SYNO.Personal.MailAccount.Mail';

    /** @var array<string, int> */
    protected array $methods = [
        'send' => 1,
        'status' => 1,
        'stop' => 1,
        'clean' => 1,
    ];
}
