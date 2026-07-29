<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\MailPlusServer\Api;

use Sejongtf\Synology\Api;

class Alias extends Api
{
    const API_NAME = 'SYNO.MailPlusServer.Alias';

    /** @var array<string, int> */
    protected array $methods = [
        'list' => 2,
        'set' => 1,
        'create' => 1,
        'delete' => 1,
        'delete_member' => 1,
        'import' => 1,
        'export' => 1,
    ];

    public function list(int $domain_id, int $offset = 0, int $limit = -1, string $query = '', array $additional = [])
    {
        return $this->request('list', 2, [
            'domain_id' => $domain_id,
            'offset' => $offset,
            'limit' => $limit,
            'query' => $query,
            'additional' => array_values($additional),
        ]);
    }
}
