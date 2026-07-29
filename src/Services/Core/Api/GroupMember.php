<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Core\Api;

use Sejongtf\Synology\Api;

/**
 * @method array list(array $params)
 */
class GroupMember extends Api
{
    const API_NAME = 'SYNO.Core.Group.Member';

    protected array $methods = [
        'list' => 1, // group(필수): 그룹명, ingroup: bool
    ];
}
