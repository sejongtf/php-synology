<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Tests;

use PHPUnit\Framework\TestCase;
use Sejongtf\Synology\Message\Response;
use Sejongtf\Synology\Exceptions\ApiException;
use Sejongtf\Synology\Services\Chat\Api\External;
use Sejongtf\Synology\Services\Chat\Chat;
use Sejongtf\Synology\Tests\Fixtures\FakeConnection;
use Sejongtf\Synology\Tests\Fixtures\FakeResponse;

class ExternalTest extends TestCase
{
    public function test_incoming_sends_token_and_encoded_payload(): void
    {
        $client = new FakeConnection;

        (new Chat($client))->external->incoming('tok', 'hello', 'https://example.com/a.png');

        $call = $client->lastCall();

        $this->assertSame('SYNO.Chat.External', $call['api']);
        $this->assertSame(2, $call['version']);
        $this->assertSame('incoming', $call['method']);
        $this->assertSame('tok', $call['params']['token']);
        $this->assertSame(
            ['text' => 'hello', 'file_url' => 'https://example.com/a.png'],
            json_decode($call['params']['payload'], true),
            'payload 는 JSON 문자열로 인코딩되어야 한다.',
        );
    }

    public function test_external_does_not_attach_session_id(): void
    {
        // FakeConnection 는 세션 ID 를 가지고 있지만 External 은 봇 토큰 인증이므로 _sid 를 붙이면 안 된다.
        $client = new FakeConnection;

        (new Chat($client))->external->channel_list('tok');

        $this->assertArrayNotHasKey('_sid', $client->lastCall()['params']);
    }

    /**
     * @return iterable<string, array{0: ?string, 1: bool}>
     */
    public static function fileUrlProvider(): iterable
    {
        yield 'https' => ['https://example.com/a.png', true];
        yield 'http' => ['http://example.com/a.png', true];
        yield 'null' => [null, false];
        yield 'empty' => ['', false];
        yield 'plain text' => ['not a url', false];
        yield 'non-http scheme' => ['ftp://example.com/a.png', false];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('fileUrlProvider')]
    public function test_file_url_is_attached_only_for_http_urls(?string $fileUrl, bool $expected): void
    {
        $client = new FakeConnection;

        (new Chat($client))->external->chatbot('tok', 7, 'hi', $fileUrl);

        $payload = json_decode($client->lastCall()['params']['payload'], true);

        $this->assertSame($expected, array_key_exists('file_url', $payload));
        $this->assertSame(['user_ids' => [7], 'text' => 'hi'], array_diff_key($payload, ['file_url' => null]));
    }

    public function test_post_list_omits_optional_counts_without_post_id(): void
    {
        $client = new FakeConnection;

        (new Chat($client))->external->post_list('tok', 42, null, 10, 10);

        $params = $client->lastCall()['params'];

        $this->assertSame(['token' => 'tok', 'channel_id' => 42], $params);
    }

    public function test_post_file_get_returns_raw_response(): void
    {
        $client = new FakeConnection(FakeResponse::binary('PNGDATA'));

        $response = (new Chat($client))->external->post_file_get('tok', 9);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('PNGDATA', $response->body());
    }

    public function test_post_file_get_throws_on_json_error_response(): void
    {
        // 파일 대신 JSON 오류가 내려오는 경우(잘못된 토큰, 없는 post_id 등)를 조용히 넘기면 안 된다.
        $client = new FakeConnection(FakeResponse::error(['code' => 119]));

        $this->expectException(ApiException::class);

        (new Chat($client))->external->post_file_get('tok', 9);
    }

    public function test_failed_response_throws(): void
    {
        $client = new FakeConnection(FakeResponse::error(['code' => 105]));

        $this->expectException(ApiException::class);

        (new Chat($client))->external->user_list('tok');
    }

    public function test_returns_null_without_a_connection(): void
    {
        $this->assertNull((new External)->channel_list('tok'));
    }
}
