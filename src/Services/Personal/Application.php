<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Personal;

use Sejongtf\Synology\Service;

/**
 * @property \Sejongtf\Synology\Services\Personal\Api\Application\Info $info
 */
class Application extends Service
{
    /** @var array<string,\Sejongtf\Synology\Api|string|callable> */
    protected array $apis = [
        'info' => Api\Application\Info::class,
    ];
}
