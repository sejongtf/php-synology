<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Chat\Api;

use Sejongtf\Synology\Api;
use Sejongtf\Synology\Concerns\NormalizesParams;

class Post extends Api
{
    use NormalizesParams;

    const API_NAME = 'SYNO.Chat.Post';

    protected array $methods = [
        'list' => 5,
        'delete' => 8,
    ];

    /**
     * 채팅 메시지 목록 조회
     *
     * @return array|null
     */
    public function list(
        int $channel_id,
        ?int $post_id,
        ?int $prev_count = 30,
        ?int $next_count = 30,
        ?int $create_at = null
    ) {
        $params = [
            'channel_id' => $channel_id,
            'post_id' => $post_id,
            'prev_count' => $prev_count,
            'next_count' => $next_count,
            'create_at' => $create_at,
        ];

        return $this->request(__FUNCTION__, $this->methods[__FUNCTION__], $params);
    }

    /**
     * 채팅 메시지 삭제
     *
     * @param  int  $post_id  메시지 ID
     * @param  bool  $real_delete  실제 삭제 여부
     * @return array|null
     */
    public function delete(
        int $post_id,
        bool $real_delete = false
    ) {
        $params = [
            'post_id' => $post_id,
            'real_delete' => self::asBool($real_delete),
        ];

        return $this->request(__FUNCTION__, $this->methods[__FUNCTION__], $params);
    }
}
