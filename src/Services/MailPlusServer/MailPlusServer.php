<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\MailPlusServer;

use Sejongtf\Synology\Service;

class MailPlusServer extends Service
{
    /** @var array<string,\Sejongtf\Synology\Api|string|callable> */
    protected array $apis = [
        'account_detail' => Api\AccountDetail::class,
        'account_quota' => Api\AccountQuota::class,
        'alias' => Api\Alias::class,
        'delegation' => Api\Delegation::class,
        'domain' => Api\Domain::class,
        'domain_group' => Api\DomainGroup::class,
        'domain_settings' => Api\DomainSettings::class,
        'domain_user' => Api\DomainUser::class,
        'log' => Api\Log::class,
        'log_mail' => Api\LogMail::class,
        'personal_auto_reply' => Api\PersonalAutoReply::class,
        'personal_forward' => Api\PersonalForward::class,
        'util' => Api\Util::class,
        'version' => Api\Version::class,
    ];
}
