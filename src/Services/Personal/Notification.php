<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Personal;

use Sejongtf\Synology\Service;

/**
 * @property \Sejongtf\Synology\Services\Personal\Api\Notification\Conf $conf
 * @property \Sejongtf\Synology\Services\Personal\Api\Notification\Device $device
 * @property \Sejongtf\Synology\Services\Personal\Api\Notification\Event $event
 * @property \Sejongtf\Synology\Services\Personal\Api\Notification\Filter $filter
 * @property \Sejongtf\Synology\Services\Personal\Api\Notification\GDPR $gdpr
 * @property \Sejongtf\Synology\Services\Personal\Api\Notification\Identifier $identifier
 * @property \Sejongtf\Synology\Services\Personal\Api\Notification\Mobile $mobile
 * @property \Sejongtf\Synology\Services\Personal\Api\Notification\Settings $settings
 * @property \Sejongtf\Synology\Services\Personal\Api\Notification\Token $token
 * @property \Sejongtf\Synology\Services\Personal\Api\Notification\VapidPublicKey $vapid_public_key
 */
class Notification extends Service
{
    /** @var array<string,\Sejongtf\Synology\Api|string|callable> */
    protected array $apis = [
        'conf' => Api\Notification\Conf::class,
        'device' => Api\Notification\Device::class,
        'event' => Api\Notification\Event::class,
        'filter' => Api\Notification\Filter::class,
        'gdpr' => Api\Notification\GDPR::class,
        'identifier' => Api\Notification\Identifier::class,
        'mobile' => Api\Notification\Mobile::class,
        'settings' => Api\Notification\Settings::class,
        'token' => Api\Notification\Token::class,
        'vapid_public_key' => Api\Notification\VapidPublicKey::class,
    ];
}
