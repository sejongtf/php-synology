<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Calendar\Api;

use Sejongtf\Synology\Api;

/**
 * SYNO.Cal.Chatbot
 *
 * tools/generate-apis.php 가 레지스트리에서 만든 뼈대다. 레지스트리에는 파라미터
 * 시그니처가 없으므로 메서드는 매직 호출(`$api->method([...])`)로 쓴다.
 * 파라미터를 명시하고 싶으면 이 파일에 직접 메서드를 적으면 된다 — 한 번 있는 파일은
 * 생성기가 다시 건드리지 않는다.
 *
 * @method mixed cancel(array $params = [])
 * @method mixed receive(array $params = [])
 * @method mixed send(array $params = [])
 */
class Chatbot extends Api
{
    const API_NAME = 'SYNO.Cal.Chatbot';

    /** @var array<string, int> */
    protected array $methods = [
        'cancel' => 1,
        'receive' => 1,
        'send' => 1,
    ];
}
