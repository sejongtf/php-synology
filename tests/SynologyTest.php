<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Tests;

use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sejongtf\Synology\Auth\CallableStore;
use Sejongtf\Synology\Auth\InMemoryStore;
use Sejongtf\Synology\Auth\Session;
use Sejongtf\Synology\Exceptions\AuthException;
use Sejongtf\Synology\Services\Chat\Chat;
use Sejongtf\Synology\Services\Contacts\Contacts;
use Sejongtf\Synology\Synology;
use Sejongtf\Synology\Tests\Fixtures\FakePsrClient;

class SynologyTest extends TestCase
{
    private const URL = 'https://nas.example.com:5001';

    private const LOGIN_OK = '{"success":true,"data":{"sid":"NEW_SID","synotoken":"CSRF","did":"DEVICE"}}';

    private const CALL_OK = '{"success":true,"data":{}}';

    // --- (A) sid 직접 주입 ---------------------------------------------------

    /**
     * 이 파일에서 가장 중요한 테스트. sid 를 주입했으면 로그인 요청이
     * **한 번도** 나가면 안 된다.
     */
    public function test_an_injected_sid_never_triggers_a_login(): void
    {
        $http = new FakePsrClient;
        $syno = Synology::withSession(self::URL, 'INJECTED_SID', $http, new Psr17Factory);

        $syno->contacts->info->get_timezone();

        $this->assertSame(['SYNO.Contacts.Info'], $http->calledApis());
        $this->assertSame('INJECTED_SID', $http->lastBody()['_sid']);
    }

    public function test_a_session_object_carries_its_syno_token(): void
    {
        $http = new FakePsrClient;
        $syno = Synology::withSession(self::URL, new Session('SID', 'CSRF'), $http, new Psr17Factory);

        $syno->contacts->info->get_timezone();

        $this->assertSame('CSRF', $http->lastQuery()['SynoToken']);
    }

    public function test_without_credentials_there_is_no_authenticator(): void
    {
        $syno = Synology::withSession(self::URL, 'SID', new FakePsrClient, new Psr17Factory);

        $this->assertNull($syno->authenticator());
    }

    // --- (B) 외부 저장소 -----------------------------------------------------

    public function test_reads_the_sid_from_an_external_store(): void
    {
        $http = new FakePsrClient;
        $syno = Synology::withStore(
            self::URL,
            new CallableStore(get: fn () => 'FROM_CACHE'),
            $http,
            new Psr17Factory,
        );

        $syno->contacts->info->get_timezone();

        $this->assertSame(['SYNO.Contacts.Info'], $http->calledApis());
        $this->assertSame('FROM_CACHE', $http->lastBody()['_sid']);
    }

    // --- (C) 로그인 ----------------------------------------------------------

    public function test_connect_does_not_hit_the_network_until_it_has_to(): void
    {
        $http = FakePsrClient::sequence(self::LOGIN_OK, self::CALL_OK);

        Synology::connect(self::URL, 'user', 'pass', http: $http, requests: new Psr17Factory);

        // 서비스 컨테이너 부팅 중에 로그인이 일어나면 곤란하다.
        $this->assertSame([], $http->calledApis());
    }

    /**
     * `$auth = false` 인 API 는 자격증명이 있어도 **로그인을 유발하면 안 된다.**
     *
     * `connect()` 는 세션 저장소를 `AuthenticatingStore` 로 감싸서 `get()` 이 불리는
     * 순간 로그인하게 해 둔다. 그래서 봇 토큰만 쓰는 워크로드가 실수로 매 요청마다
     * DSM 로그인을 하게 되기 쉽다. 이걸 막는 건 두 곳뿐이다 —
     * `Api::raw()` 가 `$auth` 를 보고 `getSid()` 자체를 안 부르는 것, 그리고
     * `Http\Connection::request()` 가 `isset($request['_sid'])` 를 **먼저** 보고
     * 나서야 저장소를 건드리는 단락 평가. 순서가 뒤집히면 조용히 깨진다.
     */
    public function test_a_bot_token_api_never_triggers_the_lazy_login(): void
    {
        $http = FakePsrClient::sequence(self::CALL_OK);
        $syno = Synology::connect(self::URL, 'user', 'pass', http: $http, requests: new Psr17Factory);

        $syno->chat->external->incoming('BOT_TOKEN', '안녕하세요');

        $this->assertSame(['SYNO.Chat.External'], $http->calledApis());
        $this->assertArrayNotHasKey('_sid', $http->lastParams());
        $this->assertArrayNotHasKey('SynoToken', $http->lastParams());
    }

    public function test_logs_in_on_the_first_request_and_reuses_the_session(): void
    {
        $http = FakePsrClient::sequence(self::LOGIN_OK, self::CALL_OK, self::CALL_OK);
        $syno = Synology::connect(self::URL, 'user', 'pass', http: $http, requests: new Psr17Factory);

        $syno->contacts->info->get_timezone();
        $syno->contacts->info->get_timezone();

        $this->assertSame(
            ['SYNO.API.Auth', 'SYNO.Contacts.Info', 'SYNO.Contacts.Info'],
            $http->calledApis(),
        );

        // 로그인 응답의 sid 와 synotoken 이 이후 요청에 실린다.
        $this->assertSame('NEW_SID', $http->lastBody()['_sid']);
        $this->assertSame('CSRF', $http->lastQuery()['SynoToken']);
    }

    public function test_login_sends_the_parameter_names_the_guide_documents(): void
    {
        $http = FakePsrClient::sequence(self::LOGIN_OK, self::CALL_OK);
        $syno = Synology::connect(
            self::URL,
            'user',
            'pass',
            session: 'Contacts',
            otpCode: '123456',
            deviceName: 'my-server',
            rememberDevice: true,
            http: $http,
            requests: new Psr17Factory,
        );

        $syno->contacts->info->get_timezone();

        $login = FakePsrClient::paramsOf($http->requests[0]);

        $this->assertSame('SYNO.API.Auth', $login['api']);
        $this->assertSame('login', $login['method']);
        $this->assertSame('6', $login['version']);
        $this->assertSame('user', $login['account']);
        $this->assertSame('pass', $login['passwd']);
        $this->assertSame('Contacts', $login['session']);
        $this->assertSame('123456', $login['otp_code']);
        $this->assertSame('yes', $login['enable_syno_token']);
        $this->assertSame('yes', $login['enable_device_token']);
        $this->assertSame('my-server', $login['device_name']);

        // sid 를 쿠키가 아니라 JSON 으로 받아야 쿠키 저장소에 의존하지 않는다.
        $this->assertSame('sid', $login['format']);

        // 로그인 요청 자체에는 세션이 없다.
        $this->assertArrayNotHasKey('_sid', $login);
    }

    public function test_optional_login_parameters_are_omitted_when_unused(): void
    {
        $http = FakePsrClient::sequence(self::LOGIN_OK, self::CALL_OK);
        $syno = Synology::connect(self::URL, 'user', 'pass', http: $http, requests: new Psr17Factory);

        $syno->contacts->info->get_timezone();

        $login = FakePsrClient::paramsOf($http->requests[0]);

        foreach (['session', 'otp_code', 'device_id', 'device_name', 'enable_device_token'] as $key) {
            $this->assertArrayNotHasKey($key, $login);
        }
    }

    public function test_a_failed_login_surfaces_as_an_auth_exception(): void
    {
        $http = FakePsrClient::sequence('{"success":false,"error":{"code":403}}');
        $syno = Synology::connect(self::URL, 'user', 'pass', http: $http, requests: new Psr17Factory);

        try {
            $syno->contacts->info->get_timezone();
            $this->fail('AuthException 이 발생해야 한다.');
        } catch (AuthException $e) {
            $this->assertTrue($e->requiresOtp());
            $this->assertStringContainsString('2-factor authentication code required', $e->getMessage());
        }
    }

    // --- (C-2) 2단계 인증: device token 과 OTP 코드 ---------------------------

    /**
     * 만료되는 건 세션이지 기기 등록이 아니다.
     *
     * 로그인 응답의 `did` 를 챙겨 두지 않으면, 2단계 인증이 강제된 계정에서 세션이
     * 만료됐을 때 재로그인이 OTP 를 요구해 실패한다. 자동 재시도 경로가 통째로 죽는다.
     */
    public function test_the_device_token_from_a_login_is_reused_on_the_next_one(): void
    {
        $http = FakePsrClient::sequence(
            self::LOGIN_OK,                              // did: DEVICE
            '{"success":false,"error":{"code":106}}',
            self::LOGIN_OK,
            self::CALL_OK,
        );
        $syno = Synology::connect(
            self::URL, 'user', 'pass',
            otpCode: '123456',
            rememberDevice: true,
            http: $http,
            requests: new Psr17Factory,
        );

        $syno->contacts->info->get_timezone();

        $first = FakePsrClient::paramsOf($http->requests[0]);
        $second = FakePsrClient::paramsOf($http->requests[2]);

        $this->assertArrayNotHasKey('device_id', $first);
        $this->assertSame('DEVICE', $second['device_id']);
    }

    /**
     * did 를 이 인스턴스가 직접 받은 적이 없어도 된다. 다른 프로세스가 로그인해
     * 저장소에 넣어 둔 세션에서도 챙겨야 한다 — 버리기 전에.
     */
    public function test_a_device_token_in_the_store_survives_the_refresh(): void
    {
        $http = FakePsrClient::sequence(
            '{"success":false,"error":{"code":106}}',    // 저장소의 세션이 이미 만료
            self::LOGIN_OK,
            self::CALL_OK,
        );
        $syno = Synology::connect(
            self::URL, 'user', 'pass',
            store: new InMemoryStore(new Session('STORED_SID', did: 'STORED_DEVICE')),
            http: $http,
            requests: new Psr17Factory,
        );

        $syno->contacts->info->get_timezone();

        $this->assertSame('STORED_DEVICE', FakePsrClient::paramsOf($http->requests[1])['device_id']);
    }

    /**
     * OTP 코드는 일회용이다. 재로그인에 같은 코드를 또 보내면 404(코드 인증 실패)가
     * 오고, 정작 필요한 신호인 403(코드 필요)이 가려진다.
     */
    public function test_a_used_otp_code_is_not_replayed(): void
    {
        $http = FakePsrClient::sequence(
            self::LOGIN_OK,
            '{"success":false,"error":{"code":106}}',
            self::LOGIN_OK,
            self::CALL_OK,
        );
        $syno = Synology::connect(
            self::URL, 'user', 'pass',
            otpCode: '123456',
            http: $http,
            requests: new Psr17Factory,
        );

        $syno->contacts->info->get_timezone();

        $this->assertSame('123456', FakePsrClient::paramsOf($http->requests[0])['otp_code']);
        $this->assertArrayNotHasKey('otp_code', FakePsrClient::paramsOf($http->requests[2]));
    }

    /**
     * 403 을 잡아 사용자에게 코드를 받고 다시 시도하는 흐름.
     */
    public function test_an_otp_code_can_be_given_for_a_retry(): void
    {
        $http = FakePsrClient::sequence(
            '{"success":false,"error":{"code":403}}',    // 2단계 인증 코드 필요
            self::LOGIN_OK,
            self::CALL_OK,
        );
        $syno = Synology::connect(self::URL, 'user', 'pass', http: $http, requests: new Psr17Factory);

        try {
            $syno->contacts->info->get_timezone();
            $this->fail('AuthException 이 발생해야 한다.');
        } catch (AuthException $e) {
            $this->assertTrue($e->requiresOtp());
        }

        $syno->authenticator()->login('654321');
        $syno->contacts->info->get_timezone();

        $this->assertSame('654321', FakePsrClient::paramsOf($http->requests[1])['otp_code']);
        $this->assertSame('NEW_SID', $http->lastBody()['_sid']);
    }

    // --- 세션 만료 재시도 ----------------------------------------------------

    public function test_an_expired_session_is_retried_once_with_a_fresh_login(): void
    {
        $http = FakePsrClient::sequence(
            self::LOGIN_OK,                              // 최초 지연 로그인
            '{"success":false,"error":{"code":106}}',    // Session timeout
            '{"success":true,"data":{"sid":"SECOND_SID","synotoken":"CSRF2"}}',
            self::CALL_OK,                               // 새 세션으로 재시도
        );
        $syno = Synology::connect(self::URL, 'user', 'pass', http: $http, requests: new Psr17Factory);

        $syno->contacts->info->get_timezone();

        $this->assertSame(
            ['SYNO.API.Auth', 'SYNO.Contacts.Info', 'SYNO.API.Auth', 'SYNO.Contacts.Info'],
            $http->calledApis(),
        );

        // 재시도는 새 sid 와 새 SynoToken 으로 나가야 한다.
        $this->assertSame('SECOND_SID', $http->lastBody()['_sid']);
        $this->assertSame('CSRF2', $http->lastQuery()['SynoToken']);
    }

    public function test_sid_not_found_is_treated_the_same_way(): void
    {
        $http = FakePsrClient::sequence(
            self::LOGIN_OK,
            '{"success":false,"error":{"code":119}}',
            self::LOGIN_OK,
            self::CALL_OK,
        );
        $syno = Synology::connect(self::URL, 'user', 'pass', http: $http, requests: new Psr17Factory);

        $syno->contacts->info->get_timezone();

        $this->assertCount(4, $http->requests);
    }

    /**
     * 107 = 같은 계정으로 다른 곳에서 로그인해 이 세션이 끊긴 것. 106/119 와 대처가 같다.
     */
    public function test_a_duplicated_login_also_reauthenticates(): void
    {
        $http = FakePsrClient::sequence(
            self::LOGIN_OK,
            '{"success":false,"error":{"code":107}}',
            self::LOGIN_OK,
            self::CALL_OK,
        );
        $syno = Synology::connect(self::URL, 'user', 'pass', http: $http, requests: new Psr17Factory);

        $syno->contacts->info->get_timezone();

        $this->assertCount(4, $http->requests);
    }

    public function test_the_retry_happens_only_once(): void
    {
        // 다시 로그인해도 계속 만료라면 무한히 돌면 안 된다.
        $http = FakePsrClient::sequence(
            self::LOGIN_OK,
            '{"success":false,"error":{"code":106}}',
            self::LOGIN_OK,
            '{"success":false,"error":{"code":106}}',
        );
        $syno = Synology::connect(self::URL, 'user', 'pass', http: $http, requests: new Psr17Factory);

        $response = $syno->contacts->info->raw('get_timezone');

        $this->assertCount(4, $http->requests);
        $this->assertFalse($response->success());
    }

    /**
     * sid 만 주입한 경우 라이브러리에는 다시 로그인할 자격증명이 없다.
     * 조용히 실패시키지 말고 오류를 그대로 올려서 소비자가 갱신하게 한다.
     */
    public function test_without_credentials_an_expired_session_is_not_retried(): void
    {
        $http = FakePsrClient::sequence('{"success":false,"error":{"code":106}}');
        $syno = Synology::withSession(self::URL, 'INJECTED_SID', $http, new Psr17Factory);

        $response = $syno->contacts->info->raw('get_timezone');

        $this->assertSame(['SYNO.Contacts.Info'], $http->calledApis());

        // 106 은 어느 서비스에서 나와도 그 서비스의 오류가 아니라 세션 문제다.
        // catch (AuthException) 하나로 세션 갱신을 처리할 수 있어야 한다.
        $this->expectException(AuthException::class);
        $response->throw();
    }

    #[DataProvider('sessionExpiredCodes')]
    public function test_every_session_code_surfaces_as_an_auth_exception(int $code): void
    {
        $http = FakePsrClient::sequence('{"success":false,"error":{"code":'.$code.'}}');
        $syno = Synology::withSession(self::URL, 'INJECTED_SID', $http, new Psr17Factory);

        // Calendar 는 자기 코드 표(CalendarException)를 갖고 있지만, 세션 코드만은 예외다.
        $this->expectException(AuthException::class);
        $syno->calendar->cal->raw('list')->throw();
    }

    public static function sessionExpiredCodes(): array
    {
        return [[106], [107], [119]];
    }

    // --- 서비스 접근 ---------------------------------------------------------

    public function test_resolves_services_and_caches_them(): void
    {
        $syno = Synology::withSession(self::URL, 'SID', new FakePsrClient, new Psr17Factory);

        $this->assertInstanceOf(Contacts::class, $syno->contacts);
        $this->assertInstanceOf(Chat::class, $syno->chat);
        $this->assertSame($syno->chat, $syno->chat);
    }

    public function test_accepts_both_snake_case_and_camel_case(): void
    {
        $syno = Synology::withSession(self::URL, 'SID', new FakePsrClient, new Psr17Factory);

        $this->assertSame($syno->mail_account, $syno->mailAccount);
        $this->assertSame($syno->mail_plus_server, $syno->mailPlusServer);
        $this->assertTrue(isset($syno->mailPlusServer));
    }

    public function test_an_unknown_service_says_so(): void
    {
        $syno = Synology::withSession(self::URL, 'SID', new FakePsrClient, new Psr17Factory);

        $this->expectException(InvalidArgumentException::class);
        $syno->video_station;
    }

    // --- 디스커버리 ----------------------------------------------------------

    public function test_discovery_feeds_paths_back_into_the_connection(): void
    {
        $http = FakePsrClient::sequence(
            '{"success":true,"data":{"SYNO.VideoStation.Info":{"path":"VideoStation/info.cgi","minVersion":1,"maxVersion":1}}}',
            self::CALL_OK,
        );
        $syno = Synology::withSession(self::URL, 'SID', $http, new Psr17Factory);

        $registry = $syno->discover();

        $this->assertSame('VideoStation/info.cgi', $registry->path('SYNO.VideoStation.Info'));
        $this->assertSame('VideoStation/info.cgi', $syno->connection()->getRegistry()->path('SYNO.VideoStation.Info'));

        // 디스커버리는 로그인 전에도 되어야 하므로 _sid 를 붙이지 않는다.
        $params = FakePsrClient::paramsOf($http->requests[0]);
        $this->assertSame('SYNO.API.Info', $params['api']);
        $this->assertArrayNotHasKey('_sid', $params);
    }
}
