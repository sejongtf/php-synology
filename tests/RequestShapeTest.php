<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Tests;

use BadMethodCallException;
use PHPUnit\Framework\TestCase;
use Sejongtf\Synology\Services\Chat\Api\External;
use Sejongtf\Synology\Services\MailPlusServer\Api\Util;
use Sejongtf\Synology\Services\Personal\Api\Profile\Photo;
use Sejongtf\Synology\Tests\Fixtures\FakeConnection;
use Sejongtf\Synology\Tests\Fixtures\FakeResponse;

/**
 * 실제로 나가는 요청의 모양을 고정한다.
 *
 * 여기 있는 것들은 전부 한 번 틀렸던 자리다 — DSM 이 조용히 거부하거나
 * 인증 없이 요청이 나가버려서 단위 테스트로는 안 잡히던 종류다.
 */
class RequestShapeTest extends TestCase
{

    public function test_profile_photo_attaches_the_session_id(): void
    {
        $client = new FakeConnection(FakeResponse::binary());
        (new Photo($client))->get(42);

        $this->assertSame('SID123', $client->lastCall()['params']['_sid']);
    }

    public function test_list_user_group_sends_its_own_method_name(): void
    {
        $client = new FakeConnection;
        (new Util($client))->list_user_group(1, type: 'user');

        $call = $client->lastCall();

        // 복붙으로 'list_user' 를 보내고 있었다.
        $this->assertSame('list_user_group', $call['method']);
        $this->assertSame('user', $call['params']['type']);
    }

    public function test_list_user_group_omits_an_empty_type(): void
    {
        $client = new FakeConnection;
        (new Util($client))->list_user_group(1);

        $this->assertArrayNotHasKey('type', $client->lastCall()['params']);
    }

    public function test_magic_call_does_not_reach_non_public_methods(): void
    {
        $external = new External(new FakeConnection);

        // send()/isUrl() 은 private 이다. __call 이 이걸 넘겨주면 가시성을 우회하게 된다.
        $this->expectException(BadMethodCallException::class);

        $external->send('incoming', []);
    }
}
