<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\MailPlusServer\Api;

use Sejongtf\Synology\Api;

class DomainGroup extends Api
{
    const API_NAME = 'SYNO.MailPlusServer.Domain.Group';

    /** @var array<string, int> */
    protected array $methods = [
        'list' => 1,
        'set' => 1,
        'delete' => 1,
        'get_member_status' => 1,
        'restore_member_status' => 1,
        'restore_partial_member_status' => 1,
    ];

    public function list(int $domain_id, int $offset = 0, int $limit = -1, string $action = 'enum', string $query = '')
    {
        return $this->request('list', 1, compact('domain_id', 'offset', 'limit', 'action', 'query'));
    }

    public function get_member_status(int $domain_id, int $group_id)
    {
        return $this->request('get_member_status', 1, ['domain_id' => $domain_id, 'id' => $group_id]);
    }
}
