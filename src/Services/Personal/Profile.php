<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Personal;

use Sejongtf\Synology\Service;

/**
 * @property \Sejongtf\Synology\Services\Personal\Api\Profile\Photo $photo
 * @property \Sejongtf\Synology\Services\Personal\Api\Profile\Profile $profile
 */
class Profile extends Service
{
    /** @var array<string,\Sejongtf\Synology\Api|string|callable> */
    protected array $apis = [
        'photo' => Api\Profile\Photo::class,
        'profile' => Api\Profile\Profile::class,
    ];
}
