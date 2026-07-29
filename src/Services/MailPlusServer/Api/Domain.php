<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\MailPlusServer\Api;

use Sejongtf\Synology\Api;

class Domain extends Api
{
    const API_NAME = 'SYNO.MailPlusServer.Domain';

    /** @var array<string, int> */
    protected array $methods = [
        'list' => 1,
        'create' => 1,
        'create_primary' => 1,
        'set' => 1,
        'set_primary' => 1,
        'delete' => 1,
        'has_domain_admin' => 1,
        'migrate' => 1,

        'set_security' => 2,
        'get_security' => 2,
        'generate_dkim_key' => 2,
    ];
}
