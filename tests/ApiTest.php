<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Tests;

use PHPUnit\Framework\TestCase;
use Sejongtf\Synology\Tests\Fixtures\FakeConnection;
use Sejongtf\Synology\Tests\Fixtures\StubApi;
use Sejongtf\Synology\Tests\Fixtures\UnauthenticatedStubApi;

class ApiTest extends TestCase
{
    public function test_attaches_session_id_when_auth_is_enabled(): void
    {
        $client = new FakeConnection;

        (new StubApi($client))->request('get');

        $this->assertSame('SID123', $client->lastCall()['params']['_sid']);
    }

    public function test_omits_session_id_when_auth_is_disabled(): void
    {
        $client = new FakeConnection;

        (new UnauthenticatedStubApi($client))->request('get');

        $this->assertArrayNotHasKey('_sid', $client->lastCall()['params']);
    }

    public function test_version_falls_back_to_methods_map_then_one(): void
    {
        $client = new FakeConnection;
        $api = new StubApi($client);

        $api->request('list');
        $this->assertSame(3, $client->lastCall()['version'], '$methods 에 선언된 버전을 써야 한다.');

        $api->request('list', 9);
        $this->assertSame(9, $client->lastCall()['version'], '명시한 버전이 우선이어야 한다.');

        $api->request('unknown_method');
        $this->assertSame(1, $client->lastCall()['version'], '선언이 없으면 1 로 폴백해야 한다.');
    }

    public function test_array_params_are_json_encoded_but_strings_pass_through(): void
    {
        $client = new FakeConnection;

        (new StubApi($client))->request('get', null, [
            'ids' => [1, 2, 3],
            'already' => '[4,5]',
            'plain' => 'text',
            'number' => 7,
        ]);

        $params = $client->lastCall()['params'];

        $this->assertSame('[1,2,3]', $params['ids']);
        $this->assertSame('[4,5]', $params['already'], '이중 인코딩이 일어나면 안 된다.');
        $this->assertSame('text', $params['plain']);
        $this->assertSame(7, $params['number']);
    }

    public function test_returns_null_without_a_connection(): void
    {
        $this->assertNull((new StubApi)->request('get'));
        $this->assertNull((new StubApi)->raw('get'));
    }

    public function test_session_id_follows_connection_replacement(): void
    {
        $api = new StubApi(new FakeConnection(null, 'OLD'));
        $replacement = new FakeConnection(null, 'NEW');

        $api->setConnection($replacement);
        $api->request('get');

        $this->assertSame('NEW', $replacement->lastCall()['params']['_sid']);
    }

    public function test_magic_call_dispatches_declared_methods(): void
    {
        $client = new FakeConnection;

        (new StubApi($client))->list(['keyword' => 'x']);

        $this->assertSame('list', $client->lastCall()['method']);
        $this->assertSame('x', $client->lastCall()['params']['keyword']);
    }

    public function test_magic_call_rejects_unknown_methods(): void
    {
        $this->expectException(\BadMethodCallException::class);

        (new StubApi(new FakeConnection))->definitely_not_a_method();
    }
}
