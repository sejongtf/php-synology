<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\MailPlusServer\Api;

use Sejongtf\Synology\Api;

class Util extends Api
{
    const API_NAME = 'SYNO.MailPlusServer.Util';

    /** @var array<string, int> */
    protected array $methods = [
        'list_user' => 1,
        'list_group' => 1,
        'list_user_group' => 1,
    ];

    public function list_user(int $domain_id, int $offset = 0, int $limit = -1)
    {
        return $this->request(__FUNCTION__, 1, compact('domain_id', 'offset', 'limit'));
    }

    public function list_user_group(int $domain_id, int $offset = 0, int $limit = -1, string $type = '')
    {
        $params = compact('domain_id', 'offset', 'limit');

        if ($type !== '') {
            $params['type'] = $type;
        }

        return $this->request(__FUNCTION__, 1, $params);
    }
}
