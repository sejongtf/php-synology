<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Calendar\Api;

use BadMethodCallException;
use Sejongtf\Synology\Api;

class Cal extends Api
{
    const API_NAME = 'SYNO.Cal.Cal';

    protected array $methods = [
        'create' => 5,
        'list' => 5,
        'get' => 5,
        'set' => 5,
        'delete' => 5,
    ];

    /**
     * @param  string  $calType  'todo', 'event' 중 하나
     * @return array
     */
    public function list(string $calType)
    {
        return $this->request('list', 5, ['cal_type' => $calType]);
    }

    /**
     * @return array
     */
    public function get(string $calId)
    {
        return $this->request('get', 5, ['cal_id' => $calId]);
    }

    /**
     * @return array
     */
    public function set(array $params)
    {
        throw new BadMethodCallException('This feature is not implemented.');
    }

    /**
     * @return array
     */
    public function delete(string $calId)
    {
        $this->request('delete', 5, ['cal_id' => $calId]);
    }
}
