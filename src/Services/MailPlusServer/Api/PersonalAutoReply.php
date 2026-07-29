<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\MailPlusServer\Api;

use Sejongtf\Synology\Api;

/**
 * SYNO.MailPlusServer.Personal.AutoReply
 *
 * tools/generate-apis.php 가 레지스트리에서 만든 뼈대다. 레지스트리에는 파라미터
 * 시그니처가 없으므로 메서드는 매직 호출(`$api->method([...])`)로 쓴다.
 * 파라미터를 명시하고 싶으면 이 파일에 직접 메서드를 적으면 된다 — 한 번 있는 파일은
 * 생성기가 다시 건드리지 않는다.
 *
 * @method mixed create_reply(array $params = [])
 * @method mixed delete_reply(array $params = [])
 * @method mixed get(array $params = [])
 * @method mixed list_reply(array $params = [])
 * @method mixed set(array $params = [])
 * @method mixed set_reply(array $params = [])
 */
class PersonalAutoReply extends Api
{
    const API_NAME = 'SYNO.MailPlusServer.Personal.AutoReply';

    /** @var array<string, int> */
    protected array $methods = [
        'create_reply' => 1,
        'delete_reply' => 1,
        'get' => 2,
        'list_reply' => 1,
        'set' => 2,
        'set_reply' => 1,
    ];
}
