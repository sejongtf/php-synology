<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Chat\Api;

use Sejongtf\Synology\Api;
use Sejongtf\Synology\Concerns\HandlesBinaryResponse;
use Sejongtf\Synology\Message\Response;

class External extends Api
{
    use HandlesBinaryResponse;

    const API_NAME = 'SYNO.Chat.External';

    // authLevel 0 — 세션을 쓰지 않는다.
    const AUTH = false;

    // 메서드명 => 버전의 형식
    protected array $methods = [
        'broadcast' => 2,
        'incoming' => 2,
        'chatbot' => 2,
        'user_list' => 2,
        'channel_list' => 2,
        'post_list' => 2,
        'post_file_get' => 2,
    ];

    /**
     * @return array|null
     */
    public function incoming(string $token, string $text, ?string $fileUrl = null)
    {
        $payload = [
            'text' => $text,
        ];

        if ($this->isUrl($fileUrl)) {
            $payload['file_url'] = $fileUrl;
        }

        return $this->send(__FUNCTION__, ['token' => $token, 'payload' => $payload]);
    }

    /**
     * 챗봇 메시지 전송
     *
     * @return array|null
     */
    public function chatbot(string $token, int $userId, string $text, ?string $fileUrl = null)
    {
        $payload = [
            'user_ids' => [$userId],
            'text' => $text,
        ];

        if ($this->isUrl($fileUrl)) {
            $payload['file_url'] = $fileUrl;
        }

        return $this->send(__FUNCTION__, ['token' => $token, 'payload' => $payload]);
    }

    /**
     * 봇을 통해 볼 수 있는 대화 채널
     *
     * @param  string  $token  봇 토큰
     * @return array|null
     */
    public function channel_list(string $token)
    {
        return $this->send(__FUNCTION__, ['token' => $token]);
    }

    /**
     * 봇을 통해 볼 수 있는 사용자 목록
     *
     * @param  string  $token  봇 토큰
     * @return array|null
     */
    public function user_list(string $token)
    {
        return $this->send(__FUNCTION__, ['token' => $token]);
    }

    /**
     * 봇을 통해 볼 수 있는 메시지
     *
     * @param  string  $token  봇 토큰
     * @param  int  $channel_id  채널 ID
     * @param  int|null  $post_id  포스트 ID
     * @param  int|null  $next_count  포스트 ID 지정 시 표시할 다음 대화 수
     * @param  int|null  $prev_count  포스트 ID 지정 시 표시할 이전 대화 수
     * @return array|null
     */
    public function post_list(string $token, int $channel_id, ?int $post_id = null, ?int $next_count = null, ?int $prev_count = null)
    {
        $params = [];
        $params['token'] = $token;
        $params['channel_id'] = $channel_id;
        if ($post_id) {
            $params['post_id'] = $post_id;
            if ($next_count) {
                $params['next_count'] = $next_count;
            }
            if ($prev_count) {
                $params['prev_count'] = $prev_count;
            }
        }

        return $this->send(__FUNCTION__, $params);
    }

    /**
     * 첨부 파일은 JSON 이 아닌 바이너리로 내려오므로 Response 를 그대로 돌려준다.
     *
     * @return Response|null
     */
    public function post_file_get(string $token, int $postId)
    {
        // 파일 대신 JSON/plaintext 가 내려왔다면 오류 응답이므로 예외로 올린다.
        return $this->binary(__FUNCTION__, $this->methods[__FUNCTION__], [
            'token' => $token,
            'post_id' => $postId,
        ]);
    }

    /**
     * @return array|null
     */
    private function send(string $method, array $params)
    {
        return $this->raw($method, $this->methods[$method], $params)?->throw()->data();
    }

    /**
     * Str::isUrl() 대체. 첨부 URL 은 http(s) 만 허용한다.
     */
    private function isUrl(?string $value): bool
    {
        if (! $value) {
            return false;
        }

        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true);
    }
}
