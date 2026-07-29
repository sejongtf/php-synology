<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Calendar\Api;

use Sejongtf\Synology\Api;

/**
 * SYNO.Cal.Contact
 *
 * tools/generate-apis.php 가 레지스트리에서 만든 뼈대다. 레지스트리에는 파라미터
 * 시그니처가 없으므로 메서드는 매직 호출(`$api->method([...])`)로 쓴다.
 * 파라미터를 명시하고 싶으면 이 파일에 직접 메서드를 적으면 된다 — 한 번 있는 파일은
 * 생성기가 다시 건드리지 않는다.
 *
 * @method mixed get_user_info(array $params = [])
 * @method mixed list(array $params = [])
 * @method mixed notify_reset_contact(array $params = [])
 * @method mixed notify_update_contact(array $params = [])
 */
class Contact extends Api
{
    const API_NAME = 'SYNO.Cal.Contact';

    /** @var array<string, int> */
    protected array $methods = [
        'get_user_info' => 1,
        'list' => 1,
        'notify_reset_contact' => 1,
        'notify_update_contact' => 1,
    ];
}
