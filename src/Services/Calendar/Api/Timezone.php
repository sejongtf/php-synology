<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Calendar\Api;

use Sejongtf\Synology\Api;

class Timezone extends Api
{
    const API_NAME = 'SYNO.Cal.Timezone';

    protected array $methods = [
        'list' => 5,
    ];
}
