<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Calendar\Api;

use Sejongtf\Synology\Api;

/**
 * @method array list(array $params)
 * @method array get(array $params)
 * @method array set(array $params)
 * @method array create(array $params)
 * @method array delete(array $params)
 * @method array search(array $params)
 */
class Todo extends Api
{
    const API_NAME = 'SYNO.Cal.Todo';

    protected array $methods = [
        'list' => 5,
        'get' => 5,
        'set' => 5,
        'create' => 5,
        'delete' => 5,
        'count_todo' => 5,
        'clean_complete' => 1,
        'list_count' => 1,
        'set_order' => 1,
        'search' => 1,
    ];
}
