<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Chat;

use Sejongtf\Synology\Service;

/**
 * @property \Sejongtf\Synology\Services\Chat\Api\AdminSetting $admin_setting
 * @property \Sejongtf\Synology\Services\Chat\Api\App $app
 * @property \Sejongtf\Synology\Services\Chat\Api\Archive $archive
 * @property \Sejongtf\Synology\Services\Chat\Api\Bot $bot
 * @property \Sejongtf\Synology\Services\Chat\Api\Channel $channel
 * @property \Sejongtf\Synology\Services\Chat\Api\ChannelAnonymous $channel_anonymous
 * @property \Sejongtf\Synology\Services\Chat\Api\ChannelChatbot $channel_chatbot
 * @property \Sejongtf\Synology\Services\Chat\Api\ChannelGuestUser $channel_guest_user
 * @property \Sejongtf\Synology\Services\Chat\Api\ChannelHashtag $channel_hashtag
 * @property \Sejongtf\Synology\Services\Chat\Api\ChannelHidden $channel_hidden
 * @property \Sejongtf\Synology\Services\Chat\Api\ChannelMember $channel_member
 * @property \Sejongtf\Synology\Services\Chat\Api\ChannelNamed $channel_named
 * @property \Sejongtf\Synology\Services\Chat\Api\ChannelPreference $channel_preference
 * @property \Sejongtf\Synology\Services\Chat\Api\Chatbot $chatbot
 * @property \Sejongtf\Synology\Services\Chat\Api\External $external
 * @property \Sejongtf\Synology\Services\Chat\Api\Misc $misc
 * @property \Sejongtf\Synology\Services\Chat\Api\Post $post
 * @property \Sejongtf\Synology\Services\Chat\Api\PostAttachment $post_attachment
 * @property \Sejongtf\Synology\Services\Chat\Api\PostFile $post_file
 * @property \Sejongtf\Synology\Services\Chat\Api\PostHashtag $post_hashtag
 * @property \Sejongtf\Synology\Services\Chat\Api\PostReaction $post_reaction
 * @property \Sejongtf\Synology\Services\Chat\Api\PostReminder $post_reminder
 * @property \Sejongtf\Synology\Services\Chat\Api\PostSchedule $post_schedule
 * @property \Sejongtf\Synology\Services\Chat\Api\PostSnippet $post_snippet
 * @property \Sejongtf\Synology\Services\Chat\Api\PostSubscribe $post_subscribe
 * @property \Sejongtf\Synology\Services\Chat\Api\PostVote $post_vote
 * @property \Sejongtf\Synology\Services\Chat\Api\Sticker $sticker
 * @property \Sejongtf\Synology\Services\Chat\Api\User $user
 * @property \Sejongtf\Synology\Services\Chat\Api\UserAvatar $user_avatar
 * @property \Sejongtf\Synology\Services\Chat\Api\UserPreference $user_preference
 * @property \Sejongtf\Synology\Services\Chat\Api\UserStatus $user_status
 * @property \Sejongtf\Synology\Services\Chat\Api\WebhookBroadcast $webhook_broadcast
 * @property \Sejongtf\Synology\Services\Chat\Api\WebhookBuiltIn $webhook_built_in
 * @property \Sejongtf\Synology\Services\Chat\Api\WebhookIncoming $webhook_incoming
 * @property \Sejongtf\Synology\Services\Chat\Api\WebhookOutgoing $webhook_outgoing
 * @property \Sejongtf\Synology\Services\Chat\Api\WebhookSlash $webhook_slash
 */
class Chat extends Service
{
    /** @var array<string,\Sejongtf\Synology\Api|string|callable> */
    protected array $apis = [
        'admin_setting' => Api\AdminSetting::class,
        'app' => Api\App::class,
        'archive' => Api\Archive::class,
        'bot' => Api\Bot::class,
        'channel' => Api\Channel::class,
        'channel_anonymous' => Api\ChannelAnonymous::class,
        'channel_chatbot' => Api\ChannelChatbot::class,
        'channel_guest_user' => Api\ChannelGuestUser::class,
        'channel_hashtag' => Api\ChannelHashtag::class,
        'channel_hidden' => Api\ChannelHidden::class,
        'channel_member' => Api\ChannelMember::class,
        'channel_named' => Api\ChannelNamed::class,
        'channel_preference' => Api\ChannelPreference::class,
        'chatbot' => Api\Chatbot::class,
        'external' => Api\External::class,
        'misc' => Api\Misc::class,
        'post' => Api\Post::class,
        'post_attachment' => Api\PostAttachment::class,
        'post_file' => Api\PostFile::class,
        'post_hashtag' => Api\PostHashtag::class,
        'post_reaction' => Api\PostReaction::class,
        'post_reminder' => Api\PostReminder::class,
        'post_schedule' => Api\PostSchedule::class,
        'post_snippet' => Api\PostSnippet::class,
        'post_subscribe' => Api\PostSubscribe::class,
        'post_vote' => Api\PostVote::class,
        'sticker' => Api\Sticker::class,
        'user' => Api\User::class,
        'user_avatar' => Api\UserAvatar::class,
        'user_preference' => Api\UserPreference::class,
        'user_status' => Api\UserStatus::class,
        'webhook_broadcast' => Api\WebhookBroadcast::class,
        'webhook_built_in' => Api\WebhookBuiltIn::class,
        'webhook_incoming' => Api\WebhookIncoming::class,
        'webhook_outgoing' => Api\WebhookOutgoing::class,
        'webhook_slash' => Api\WebhookSlash::class,
    ];
}
