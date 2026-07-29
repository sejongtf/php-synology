<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Core;

use Sejongtf\Synology\Service;

/**
 * @property \Sejongtf\Synology\Services\Core\Api\User $user
 * @property \Sejongtf\Synology\Services\Core\Api\Group $group
 * @property \Sejongtf\Synology\Services\Core\Api\GroupMember $group_member
 */
class Core extends Service
{
    /** @var array<string,\Sejongtf\Synology\Api|string|callable> */
    protected array $apis = [
        'user' => Api\User::class,
        'group' => Api\Group::class,
        'group_member' => Api\GroupMember::class,
    ];
}
