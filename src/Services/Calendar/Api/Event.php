<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Calendar\Api;

use Sejongtf\Synology\Api;

/**
 * @method array list(array $params)
 * @method array search(array $params)
 * @method array create(array $params)
 * @method array create_simple(array $params)
 * @method array set(array $params)
 * @method array set_attach(array $params)
 * @method array get(array $params)
 * @method array reply(array $params)
 */
class Event extends Api
{
    const API_NAME = 'SYNO.Cal.Event';

    protected array $methods = [
        'create' => 5,
        'set' => 3,
        'list' => 3,
        'search' => 1,
        'delete' => 5,
        'set_attach' => 3,
        'get' => 4,
        'reply' => 1,
    ];

    public function delete($evt_id): void
    {
        $this->request('delete', 5, ['evt_id' => $evt_id]);
    }
}
