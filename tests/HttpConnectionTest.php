<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Sejongtf\Synology\Auth\InMemoryStore;
use Sejongtf\Synology\Auth\Session;
use Sejongtf\Synology\Exceptions\ApiException;
use Sejongtf\Synology\Exceptions\AuthException;
use Sejongtf\Synology\Exceptions\TransportException;
use Sejongtf\Synology\Http\Connection;
use Sejongtf\Synology\Http\Endpoint;
use Sejongtf\Synology\Registry\ApiRegistry;
use Sejongtf\Synology\Services\Calendar\CalendarException;
use Sejongtf\Synology\Services\Chat\Api\External;
use Sejongtf\Synology\Services\Contacts\Api\Info;
use Sejongtf\Synology\Tests\Fixtures\FakePsrClient;

class HttpConnectionTest extends TestCase
{
    private function connection(
        ?FakePsrClient $http = null,
        ?Session $session = null,
        ?ApiRegistry $registry = null,
    ): Connection {
        $factory = new Psr17Factory;

        return new Connection(
            $http ?? new FakePsrClient,
            $factory,
            $factory,
            new Endpoint('https://nas.example.com:5001'),
            new InMemoryStore($session),
            $registry ?? new ApiRegistry,
        );
    }

    public function test_builds_an_entry_cgi_url_with_the_envelope_params(): void
    {
        $http = new FakePsrClient;
        $this->connection($http)->request('SYNO.Contacts.Contact', 2, 'list', ['addressbook_id' => 3]);

        $uri = $http->lastRequest()->getUri();

        $this->assertSame('https', $uri->getScheme());
        $this->assertSame('nas.example.com', $uri->getHost());
        $this->assertSame(5001, $uri->getPort());
        $this->assertSame('/webapi/entry.cgi', $uri->getPath());

        // SynoToken 이 없으면 URL 에 쿼리스트링이 아예 붙지 않는다.
        $this->assertSame('', $uri->getQuery());

        $this->assertSame([
            'api' => 'SYNO.Contacts.Contact',
            'version' => '2',
            'method' => 'list',
            'addressbook_id' => '3',
        ], $http->lastBody());
    }

    public function test_the_whole_request_travels_in_a_form_body(): void
    {
        $http = new FakePsrClient;
        $this->connection($http)->request('SYNO.Contacts.Contact', 2, 'list', ['addressbook_id' => 3]);

        $request = $http->lastRequest();

        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));
        $this->assertSame([], $http->lastQuery());
    }

    /**
     * 본문이 빈 POST 는 라우팅이 쿼리에 있어도 DSM 이 101 로 거절한다(실기기 확인).
     * `api`/`version`/`method` 가 본문에 있으므로 파라미터가 없어도 본문은 비지 않는다.
     */
    public function test_a_parameterless_call_still_sends_a_body(): void
    {
        $http = new FakePsrClient;
        $this->connection($http)->request('SYNO.Contacts.Info', 1, 'get_timezone');

        $this->assertSame([
            'api' => 'SYNO.Contacts.Info',
            'version' => '1',
            'method' => 'get_timezone',
        ], $http->lastBody());
    }

    /**
     * PSR-17 은 새 요청의 본문 스트림이 쓰기 가능하다고 보장하지 않는다.
     *
     * 요청 팩토리가 스트림 팩토리를 겸하지 않고, 만들어 준 본문이 읽기 전용인
     * 구현체를 상대로도 본문이 제대로 나가야 한다. 여기서 `getBody()->write()` 로
     * 본문을 채우면 `RuntimeException` 이 난다.
     */
    public function test_it_works_with_a_factory_whose_request_body_is_not_writable(): void
    {
        $requests = new class implements RequestFactoryInterface
        {
            public function createRequest(string $method, $uri): RequestInterface
            {
                $readOnly = Stream::create(fopen('php://memory', 'r'));

                return (new Psr17Factory)->createRequest($method, $uri)->withBody($readOnly);
            }
        };

        $http = new FakePsrClient;
        $connection = new Connection(
            $http,
            $requests,
            new Psr17Factory,      // 스트림 팩토리는 따로 받는다
            new Endpoint('https://nas.example.com:5001'),
            new InMemoryStore,
        );

        $connection->request('SYNO.X', 1, 'go', ['a' => 1]);

        $this->assertSame('1', $http->lastBody()['a']);
    }

    /**
     * `SynoToken` 은 본문에 실으면 CSRF 검사가 못 보고 119 로 떨어진다(실기기 확인).
     * URL 로 나가는 건 이것 하나뿐이고, 세션 없이는 무력하다.
     */
    public function test_the_syno_token_is_the_only_thing_in_the_url(): void
    {
        $http = new FakePsrClient;
        $connection = $this->connection($http, new Session('SID123', 'CSRF_TOKEN'));

        (new Info($connection))->get_timezone();

        $this->assertSame(['SynoToken' => 'CSRF_TOKEN'], $http->lastQuery());
        $this->assertArrayNotHasKey('SynoToken', $http->lastBody());
    }

    /**
     * 로그인 자격증명이 URL 에 실리면 NAS access log 와 리버스 프록시 로그에 평문으로 남는다.
     */
    public function test_credentials_never_reach_the_url(): void
    {
        $http = new FakePsrClient;
        $this->connection($http)->request('SYNO.API.Auth', 6, 'login', [
            'account' => 'user',
            'passwd' => 's3cret',
            'otp_code' => '123456',
        ]);

        $url = (string) $http->lastRequest()->getUri();

        foreach (['user', 's3cret', '123456'] as $secret) {
            $this->assertStringNotContainsString($secret, $url);
        }

        $this->assertSame('s3cret', $http->lastBody()['passwd']);
    }

    /**
     * 본문에는 URL 길이 제한이 없다. 실측한 414 경계는 약 8,100 bytes 였다.
     */
    public function test_a_payload_larger_than_the_url_limit_still_goes_out(): void
    {
        $http = new FakePsrClient;
        $big = str_repeat('가나다라마바사', 3000);

        $this->connection($http)->request('SYNO.Chat.Post', 1, 'create', ['text' => $big]);

        $this->assertLessThan(200, strlen((string) $http->lastRequest()->getUri()));
        $this->assertSame($big, $http->lastBody()['text']);
    }

    public function test_uses_the_path_the_registry_reports(): void
    {
        // DSM 7 이라고 모든 API 가 entry.cgi 인 건 아니다.
        $http = new FakePsrClient;
        $registry = ApiRegistry::fromInfoResponse([
            'SYNO.VideoStation.Info' => ['path' => 'VideoStation/info.cgi', 'minVersion' => 1, 'maxVersion' => 1],
        ]);

        $this->connection($http, registry: $registry)->request('SYNO.VideoStation.Info', 1, 'get');

        $this->assertSame('/webapi/VideoStation/info.cgi', $http->lastRequest()->getUri()->getPath());
    }

    // --- 인증 역할 분담 ------------------------------------------------------

    public function test_the_connection_never_adds_sid_by_itself(): void
    {
        // _sid 는 Api::raw() 가 붙인다. 연결이 세션이 있다고 무조건 붙이면
        // $auth = false 인 API(봇 토큰, 로그인 전 디스커버리)의 의도가 깨진다.
        $http = new FakePsrClient;
        $this->connection($http, new Session('SID123'))->request('SYNO.Chat.External', 2, 'incoming', ['token' => 'BOT']);

        // 쿼리가 아니라 쿼리+본문 전체를 본다. 지금 배치에서 _sid 는 본문으로 나가므로
        // lastQuery() 만 보면 연결이 _sid 를 붙이도록 회귀해도 이 단정이 통과한다.
        $this->assertArrayNotHasKey('_sid', $http->lastParams());
    }

    public function test_external_stays_unauthenticated_through_the_real_connection(): void
    {
        $http = new FakePsrClient;
        $connection = $this->connection($http, new Session('SID123', 'TOK'));

        (new External($connection))->channel_list('BOT_TOKEN');

        $this->assertSame('BOT_TOKEN', $http->lastBody()['token']);
        $this->assertArrayNotHasKey('_sid', $http->lastParams());
        $this->assertArrayNotHasKey('SynoToken', $http->lastParams());
    }

    public function test_a_session_authenticated_api_carries_sid_and_syno_token(): void
    {
        $http = new FakePsrClient;
        $connection = $this->connection($http, new Session('SID123', 'CSRF_TOKEN'));

        (new Info($connection))->get_timezone();

        $this->assertSame('SID123', $http->lastBody()['_sid']);
        $this->assertSame('CSRF_TOKEN', $http->lastQuery()['SynoToken']);
    }

    public function test_syno_token_is_omitted_when_the_server_did_not_issue_one(): void
    {
        $http = new FakePsrClient;
        $connection = $this->connection($http, new Session('SID123'));

        (new Info($connection))->get_timezone();

        $this->assertSame('SID123', $http->lastBody()['_sid']);
        $this->assertSame([], $http->lastQuery());
    }

    public function test_exposes_the_session_id_for_the_api_layer(): void
    {
        $this->assertSame('SID123', $this->connection(session: new Session('SID123'))->getSessionId());
        $this->assertNull($this->connection()->getSessionId());
    }

    // --- 재인증 콜백 ---------------------------------------------------------

    /**
     * 콜백은 **방금 실패한 요청에 실려 있던** sid 를 받아야 한다. 저장소의 현재 값이
     * 아니다 — 저장소를 여러 프로세스가 공유하면 그 둘이 다를 수 있고, 다르다는 사실이
     * 곧 "그 사이 누가 이미 갱신했다" 는 신호다.
     */
    public function test_the_callback_is_told_which_sid_expired(): void
    {
        $http = FakePsrClient::sequence(
            '{"success":false,"error":{"code":106}}',
            '{"success":true,"data":{}}',
        );
        $connection = $this->connection($http, new Session('SID_NEW'));

        $seen = 'not called';
        $connection->onSessionExpired(function (string $staleSid) use (&$seen): Session {
            $seen = $staleSid;

            return new Session('SID_NEW');
        });

        // 저장소에는 SID_NEW 가 들어 있지만 이 요청이 들고 나간 건 SID_OLD 다.
        $connection->request('SYNO.Contacts.Info', 1, 'get_timezone', ['_sid' => 'SID_OLD']);

        $this->assertSame('SID_OLD', $seen);
    }

    /**
     * 다른 프로세스가 이미 갱신했으면 다시 로그인하지 않고 그 세션을 쓸 수 있어야 한다.
     * 콜백이 저장소의 세션을 그대로 돌려주면 그걸로 재시도한다.
     */
    public function test_a_session_someone_else_refreshed_can_be_reused_as_is(): void
    {
        $http = FakePsrClient::sequence(
            '{"success":false,"error":{"code":106}}',
            '{"success":true,"data":{}}',
        );
        $store = new InMemoryStore(new Session('SID_NEW', 'CSRF_NEW'));
        $factory = new Psr17Factory;
        $connection = new Connection(
            $http, $factory, $factory, new Endpoint('https://nas.example.com:5001'), $store,
        );

        $connection->onSessionExpired(
            fn (string $staleSid): Session => ($current = $store->get())->sid !== $staleSid
                ? $current                                  // 이미 갱신됐다. 로그인하지 않는다.
                : throw new \LogicException('여기 오면 안 된다.'),
        );

        $connection->request('SYNO.Contacts.Info', 1, 'get_timezone', ['_sid' => 'SID_OLD']);

        $this->assertCount(2, $http->requests);
        $this->assertSame('SID_NEW', $http->lastBody()['_sid']);
        $this->assertSame('CSRF_NEW', $http->lastQuery()['SynoToken']);
    }

    // --- 값 다듬기 -----------------------------------------------------------

    public function test_normalizes_values_that_dsm_would_misread(): void
    {
        $http = new FakePsrClient;
        $this->connection($http)->request('SYNO.X', 1, 'go', [
            'flag' => true,
            'off' => false,
            'skipped' => null,
            'list' => ['a', 'b'],
            'kept' => 'raw',
        ]);

        $body = $http->lastBody();

        // http_build_query 에 bool 을 그냥 넘기면 1/0 이 되어 조용히 잘못 읽힌다.
        $this->assertSame('true', $body['flag']);
        $this->assertSame('false', $body['off']);
        $this->assertArrayNotHasKey('skipped', $body);
        $this->assertSame('["a","b"]', $body['list']);
        $this->assertSame('raw', $body['kept']);
    }

    // --- 오류 처리 -----------------------------------------------------------

    public function test_transport_failures_become_a_transport_exception(): void
    {
        $http = (new FakePsrClient)->failWith('Connection refused');

        try {
            $this->connection($http)->request('SYNO.X', 1, 'go');
            $this->fail('TransportException 이 발생해야 한다.');
        } catch (TransportException $e) {
            $this->assertStringContainsString('SYNO.X::go', $e->getMessage());
            $this->assertStringContainsString('Connection refused', $e->getPrevious()->getMessage());
        }
    }

    /**
     * 코드 표가 겹치므로 어느 API 를 불렀는지로 예외 타입이 갈려야 한다.
     */
    public function test_picks_the_exception_class_from_the_api_name(): void
    {
        $body = '{"success":false,"error":{"code":400}}';

        $cases = [
            'SYNO.API.Auth' => AuthException::class,
            'SYNO.Cal.Event' => CalendarException::class,
            'SYNO.Core.User' => ApiException::class,
        ];

        foreach ($cases as $api => $expected) {
            $connection = $this->connection(FakePsrClient::json($body));
            $response = $connection->request($api, 1, 'list');

            try {
                $response->throw();
                $this->fail("{$api} 에서 예외가 발생해야 한다.");
            } catch (ApiException $e) {
                $this->assertInstanceOf($expected, $e, "{$api} 는 {$expected} 여야 한다.");
            }
        }
    }

    public function test_auth_400_and_file_400_produce_different_messages(): void
    {
        $body = '{"success":false,"error":{"code":400}}';

        $auth = $this->connection(FakePsrClient::json($body))->request('SYNO.API.Auth', 6, 'login');
        $file = $this->connection(FakePsrClient::json($body))->request('SYNO.Cal.Event', 3, 'list');

        try {
            $auth->throw();
        } catch (AuthException $e) {
            $this->assertStringContainsString('No such account or incorrect password', $e->getMessage());
        }

        try {
            $file->throw();
        } catch (CalendarException $e) {
            $this->assertStringContainsString('Invalid parameter of file operation', $e->getMessage());
        }
    }

    public function test_nested_error_details_reach_the_message(): void
    {
        // 바깥 1100 만 보여주면 정작 원인(408, 경로)을 알 수 없다.
        $http = FakePsrClient::json('{"success":false,"error":{"code":1100,"errors":[{"code":408,"path":"/test/:"}]}}');

        try {
            $this->connection($http)->request('SYNO.Cal.Event', 3, 'create')->throw();
            $this->fail('예외가 발생해야 한다.');
        } catch (CalendarException $e) {
            $this->assertSame(1100, $e->getErrorCode());
            $this->assertStringContainsString('408', $e->getMessage());
            $this->assertStringContainsString('/test/:', $e->getMessage());
        }
    }

    // --- Endpoint ------------------------------------------------------------

    public function test_endpoint_absorbs_a_trailing_webapi_segment(): void
    {
        $this->assertSame(
            'https://nas.example.com:5001/webapi/entry.cgi',
            (new Endpoint('https://nas.example.com:5001/webapi/'))->url('entry.cgi'),
        );
    }

    public function test_endpoint_rejects_a_url_without_a_scheme(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Endpoint('nas.example.com:5001');
    }
}
