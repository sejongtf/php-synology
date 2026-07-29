<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\MailPlusServer\Api;

use Sejongtf\Synology\Api;

/**
 * @method mixed get(array $params = [])
 * @method mixed set(array $params = [])
 * @method mixed get_domain_usage_limit(array $params = [])
 * @method mixed list_domain_usage(array $params = [])
 * @method mixed delete_domain_usage(array $params = [])
 */
class DomainSettings extends Api
{
    const API_NAME = 'SYNO.MailPlusServer.Domain.Settings';

    /** @var array<string, int> */
    protected array $methods = [
        'get' => 1,
        'set' => 1,
        'get_domain_usage_limit' => 1,
        'list_domain_usage' => 1,
        'delete_domain_usage' => 1,
    ];
}
