<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Tests;

use PHPUnit\Framework\TestCase;
use Sejongtf\Synology\Services\Chat\Api\Channel;
use Sejongtf\Synology\Services\Chat\Api\External;
use Sejongtf\Synology\Services\Chat\Chat;
use Sejongtf\Synology\Tests\Fixtures\FakeConnection;

class ServiceTest extends TestCase
{
    public function test_resolves_apis_by_property_and_array_access(): void
    {
        $chat = new Chat(new FakeConnection);

        $this->assertInstanceOf(Channel::class, $chat->channel);
        $this->assertInstanceOf(Channel::class, $chat['channel']);
        $this->assertTrue($chat->hasApi('external'));
        $this->assertNull($chat->getApi('nope'));
    }

    /**
     * 매직 접근자로 꺼낼 수 있는 건 **등록된 API 뿐이다.**
     *
     * 예전에는 `property_exists($this, $key)` 폴백이 있어서 `$chat->connection`,
     * `$chat->apis`, `$chat->resolved` 가 밖에서 그대로 읽혔다 — protected 로 선언한
     * 것이 매직 접근자 때문에 공개되는 셈이었다. `Api::__call` 이 같은 우회를 막고 있으니
     * 여기도 같아야 한다. 연결이 필요하면 `getConnection()` 이 있다.
     */
    public function test_protected_state_is_not_reachable_through_magic_access(): void
    {
        $chat = new Chat(new FakeConnection);

        foreach (['connection', 'apis', 'resolved'] as $internal) {
            $this->assertNull($chat->{$internal}, "\$chat->{$internal} 이 밖으로 노출됩니다.");
            $this->assertNull($chat[$internal], "\$chat['{$internal}'] 이 밖으로 노출됩니다.");
            $this->assertFalse(isset($chat[$internal]), "\$chat['{$internal}'] 이 존재한다고 보고합니다.");
        }

        $this->assertInstanceOf(FakeConnection::class, $chat->getConnection());
    }

    public function test_caches_api_instances(): void
    {
        $chat = new Chat(new FakeConnection);

        $this->assertSame($chat->channel, $chat->channel);
        $this->assertSame($chat->external, $chat->external);
    }

    public function test_set_connection_reinjects_into_cached_apis(): void
    {
        $chat = new Chat(new FakeConnection);
        $channel = $chat->channel;
        $external = $chat->external;

        $replacement = new FakeConnection(null, 'NEW');
        $chat->setConnection($replacement);

        // External 도 이제 Api 를 상속하므로 캐시에서 버려지지 않고 새 client 를 주입받아야 한다.
        $this->assertSame($external, $chat->external);
        $this->assertSame($replacement, $chat->external->getConnection());
        $this->assertSame($channel, $chat->channel);
        $this->assertSame($replacement, $chat->channel->getConnection());
    }

    public function test_every_registered_api_supports_set_connection(): void
    {
        $chat = new Chat(new FakeConnection);

        foreach (['channel', 'external', 'post', 'user'] as $name) {
            $this->assertTrue(
                method_exists($chat->getApi($name), 'setConnection'),
                "[{$name}] 이 setConnection() 를 지원하지 않아 setConnection() 시 캐시에서 버려집니다.",
            );
        }

        $this->assertInstanceOf(External::class, $chat->external);
    }

    public function test_offset_set_and_unset_are_rejected(): void
    {
        $chat = new Chat(new FakeConnection);

        $this->expectException(\LogicException::class);

        $chat['channel'] = null;
    }
}
