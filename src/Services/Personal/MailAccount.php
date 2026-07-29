<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Personal;

use Sejongtf\Synology\Service;

/**
 * @property \Sejongtf\Synology\Services\Personal\Api\MailAccount\Contacts $contacts
 * @property \Sejongtf\Synology\Services\Personal\Api\MailAccount\Mail $mail
 * @property \Sejongtf\Synology\Services\Personal\Api\MailAccount\MailAccount $mail_account
 */
class MailAccount extends Service
{
    /** @var array<string,\Sejongtf\Synology\Api|string|callable> */
    protected array $apis = [
        'contacts' => Api\MailAccount\Contacts::class,
        'mail' => Api\MailAccount\Mail::class,
        'mail_account' => Api\MailAccount\MailAccount::class,
    ];
}
