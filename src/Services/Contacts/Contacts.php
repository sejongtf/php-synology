<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Contacts;

use Sejongtf\Synology\Service;

/**
 * @property \Sejongtf\Synology\Services\Contacts\Api\Addressbook $addressbook
 * @property \Sejongtf\Synology\Services\Contacts\Api\AdminSetting $admin_setting
 * @property \Sejongtf\Synology\Services\Contacts\Api\Contact $contact
 * @property \Sejongtf\Synology\Services\Contacts\Api\ExternalSource $external_source
 * @property \Sejongtf\Synology\Services\Contacts\Api\Info $info
 * @property \Sejongtf\Synology\Services\Contacts\Api\Label $label
 * @property \Sejongtf\Synology\Services\Contacts\Api\OU $ou
 */
class Contacts extends Service
{
    /** @var array<string,\Sejongtf\Synology\Api|string|callable> */
    protected array $apis = [
        'addressbook' => Api\Addressbook::class,
        'admin_setting' => Api\AdminSetting::class,
        'contact' => Api\Contact::class,
        'external_source' => Api\ExternalSource::class,
        'info' => Api\Info::class,
        'label' => Api\Label::class,
        'ou' => Api\OU::class,
    ];
}
