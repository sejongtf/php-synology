<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Contacts\Api;

use Sejongtf\Synology\Api;
use Sejongtf\Synology\Concerns\NormalizesParams;

/**
 * @method array list(array $params)
 */
class Contact extends Api
{
    use NormalizesParams;

    const API_NAME = 'SYNO.Contacts.Contact';

    protected array $methods = [
        'create' => 1, // addressbook_id: ?int, contact: array, with_photo: bool = false
        'list' => 1, // addressbook_id: ?int, with_photo: bool = false
        'get' => 2,
        'set' => 1,
        'get_photo' => 1,
        'list_group' => 1,
        'delete' => 1, // ids: array<int,int>
    ];

    public function create(int $addressbook_id, array $params)
    {
        return $this->request(__FUNCTION__, $this->methods[__FUNCTION__], array_merge(compact('addressbook_id'), $params));
    }

    /**
     * @param array<string, mixed> $params
     */
    public function set(int $id, array $params)
    {
        return $this->request(__FUNCTION__, $this->methods[__FUNCTION__], array_merge(compact('id'), $params));
    }

    /**
     * @param  array<int,int>  $ids  주소록ID 배열
     */
    public function get(array $ids)
    {
        $params = [];
        $params['ids'] = self::asList($ids);

        return $this->request(__FUNCTION__, $this->methods[__FUNCTION__], $params);
    }

    /**
     * @param  array<int,int>  $contact_ids
     */
    public function get_photo(array $contact_ids)
    {
        $params = [];
        $params['contact_ids'] = $contact_ids;

        return $this->request(__FUNCTION__, $this->methods[__FUNCTION__], $params);
    }

    public function list_group(int $addressbook_id)
    {
        $params = [];
        $params['addressbook_id'] = $addressbook_id;

        return $this->request(__FUNCTION__, $this->methods[__FUNCTION__], $params);
    }

    /**
     * @param  int|array<int>  $ids
     */
    public function delete($ids)
    {
        // 웹에서는 type="SAGA_DELETE_CONTACT"라는 인수가 있는데 꼭 필요하지는 않은듯?
        $params = [];
        $params['ids'] = self::asList($ids);

        return $this->request(__FUNCTION__, $this->methods[__FUNCTION__], $params);
    }
}
