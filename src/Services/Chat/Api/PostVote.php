<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Chat\Api;

use Sejongtf\Synology\Api;

/**
 * SYNO.Chat.Post.Vote
 *
 * tools/generate-apis.php 가 레지스트리에서 만든 뼈대다. 레지스트리에는 파라미터
 * 시그니처가 없으므로 메서드는 매직 호출(`$api->method([...])`)로 쓴다.
 * 파라미터를 명시하고 싶으면 이 파일에 직접 메서드를 적으면 된다 — 한 번 있는 파일은
 * 생성기가 다시 건드리지 않는다.
 *
 * @method mixed close(array $params = [])
 * @method mixed create(array $params = [])
 * @method mixed create_option(array $params = [])
 * @method mixed delete(array $params = [])
 * @method mixed get_choices(array $params = [])
 * @method mixed set(array $params = [])
 * @method mixed vote(array $params = [])
 */
class PostVote extends Api
{
    const API_NAME = 'SYNO.Chat.Post.Vote';

    /** @var array<string, int> */
    protected array $methods = [
        'close' => 1,
        'create' => 1,
        'create_option' => 1,
        'delete' => 1,
        'get_choices' => 1,
        'set' => 1,
        'vote' => 1,
    ];
}
