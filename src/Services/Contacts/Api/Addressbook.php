<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Contacts\Api;

use Sejongtf\Synology\Api;
use Sejongtf\Synology\Concerns\NormalizesParams;

/**
 * @method array list(array $params)
 */
class Addressbook extends Api
{
    use NormalizesParams;

    const API_NAME = 'SYNO.Contacts.Addressbook';

    protected array $methods = [
        'list' => 1,
        'create' => 1,
        'delete' => 1,
    ];

    public function create(string $name, bool $is_public = true)
    {
        $params = [
            'name' => $name,
            'is_public' => self::asBool($is_public),
        ];

        return $this->request(__FUNCTION__, $this->methods[__FUNCTION__], $params);
    }

    /**
     * @param  int|array  $ids
     */
    public function delete($ids)
    {
        $params = [
            'ids' => self::asList($ids),
        ];

        return $this->request(__FUNCTION__, $this->methods[__FUNCTION__], $params);
    }
}
