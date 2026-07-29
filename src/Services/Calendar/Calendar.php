<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Calendar;

use Sejongtf\Synology\Service;

/**
 * @property \Sejongtf\Synology\Services\Calendar\Api\AuthForeign $auth_foreign
 * @property \Sejongtf\Synology\Services\Calendar\Api\Cal $cal
 * @property \Sejongtf\Synology\Services\Calendar\Api\Chatbot $chatbot
 * @property \Sejongtf\Synology\Services\Calendar\Api\Contact $contact
 * @property \Sejongtf\Synology\Services\Calendar\Api\Event $event
 * @property \Sejongtf\Synology\Services\Calendar\Api\InviteMail $invite_mail
 * @property \Sejongtf\Synology\Services\Calendar\Api\InviteMailInit $invite_mail_init
 * @property \Sejongtf\Synology\Services\Calendar\Api\Proxy $proxy
 * @property \Sejongtf\Synology\Services\Calendar\Api\SendMail $send_mail
 * @property \Sejongtf\Synology\Services\Calendar\Api\Setting $setting
 * @property \Sejongtf\Synology\Services\Calendar\Api\SharePriv $share_priv
 * @property \Sejongtf\Synology\Services\Calendar\Api\Sharing $sharing
 * @property \Sejongtf\Synology\Services\Calendar\Api\SyncUser $sync_user
 * @property \Sejongtf\Synology\Services\Calendar\Api\Timezone $timezone
 * @property \Sejongtf\Synology\Services\Calendar\Api\Todo $todo
 * @property \Sejongtf\Synology\Services\Calendar\Api\UserAction $user_action
 */
class Calendar extends Service
{
    /** @var array<string,\Sejongtf\Synology\Api|string|callable> */
    protected array $apis = [
        'auth_foreign' => Api\AuthForeign::class,
        'cal' => Api\Cal::class,
        'chatbot' => Api\Chatbot::class,
        'contact' => Api\Contact::class,
        'event' => Api\Event::class,
        'invite_mail' => Api\InviteMail::class,
        'invite_mail_init' => Api\InviteMailInit::class,
        'proxy' => Api\Proxy::class,
        'send_mail' => Api\SendMail::class,
        'setting' => Api\Setting::class,
        'share_priv' => Api\SharePriv::class,
        'sharing' => Api\Sharing::class,
        'sync_user' => Api\SyncUser::class,
        'timezone' => Api\Timezone::class,
        'todo' => Api\Todo::class,
        'user_action' => Api\UserAction::class,
    ];
}
