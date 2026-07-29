<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Personal\Api\Profile;

use Sejongtf\Synology\Api;

/**
 * @method array search(array $params)
 * @method array set(array $params)
 * @method array get(array $params)
 */
class Profile extends Api
{
    const API_NAME = 'SYNO.Personal.Profile';

    /** @var array<string, int> */
    protected array $methods = [
        'list' => 3,
        'search' => 1,
        'set' => 1,
        'get' => 3,
    ];

    /**
     * @param  array<int, int>  $users  시놀로지 유저 ID 배열
     * @return array
     *
     * @throws \Sejongtf\Synology\Exceptions\RequestException
     */
    public function list(array $users = [])
    {
        return $this->request(__FUNCTION__, 3, ['users' => array_values($users)]);
    }
}
