<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\MailPlusServer\Api;

use Sejongtf\Synology\Api;

class DomainUser extends Api
{
    const API_NAME = 'SYNO.MailPlusServer.Domain.User';

    /** @var array<string, int> */
    protected array $methods = [
        'preview' => 1,
        'add_members' => 1,
        'list' => 1,
        'set' => 1,
        'delete' => 1,
    ];

    public function list(int $domain_id, int $offset = 0, int $limit = -1, string $action = 'enum', string $query = '')
    {
        return $this->request('list', 1, compact('domain_id', 'offset', 'limit', 'action', 'query'));
    }
}
