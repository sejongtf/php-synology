<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\MailPlusServer\Api;

use Sejongtf\Synology\Api;

/**
 * SYNO.MailPlusServer.Account.Quota
 *
 * tools/generate-apis.php 가 레지스트리에서 만든 뼈대다. 레지스트리에는 파라미터
 * 시그니처가 없으므로 메서드는 매직 호출(`$api->method([...])`)로 쓴다.
 * 파라미터를 명시하고 싶으면 이 파일에 직접 메서드를 적으면 된다 — 한 번 있는 파일은
 * 생성기가 다시 건드리지 않는다.
 *
 * @method mixed clear(array $params = [])
 * @method mixed clear_group(array $params = [])
 * @method mixed get_system_quota(array $params = [])
 * @method mixed set(array $params = [])
 * @method mixed set_group(array $params = [])
 * @method mixed set_system_quota(array $params = [])
 */
class AccountQuota extends Api
{
    const API_NAME = 'SYNO.MailPlusServer.Account.Quota';

    /** @var array<string, int> */
    protected array $methods = [
        'clear' => 1,
        'clear_group' => 1,
        'get_system_quota' => 1,
        'set' => 1,
        'set_group' => 1,
        'set_system_quota' => 1,
    ];
}
