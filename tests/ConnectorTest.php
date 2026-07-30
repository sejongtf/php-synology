<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Tests;

use Closure;
use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response as PsrResponse;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Sejongtf\Synology\Auth\Authenticator;
use Sejongtf\Synology\Auth\InMemoryStore;
use Sejongtf\Synology\Auth\Session;
use Sejongtf\Synology\Synology;
use Sejongtf\Synology\Tests\Fixtures\FakePsrClient;

class ConnectorTest extends TestCase
{
    private const URL = 'https://nas.example.com:5001';

    public const LOGIN_OK = '{"success":true,"data":{"sid":"NEW_SID","synotoken":"CSRF","did":"DEVICE"}}';

    public const CALL_OK = '{"success":true,"data":{}}';

    // --- 정적 메서드와 같은 것을 만든다 ----------------------------------------

    public function test_an_injected_session_never_logs_in(): void
    {
        $http = new FakePsrClient;

        $syno = Synology::to(self::URL)
            ->session('INJECTED_SID')
            ->http($http, new Psr17Factory)
            ->connect();

        $syno->contacts->info->get_timezone();

        $this->assertSame(['SYNO.Contacts.Info'], $http->calledApis());
        $this->assertSame('INJECTED_SID', $http->lastBody()['_sid']);
        $this->assertNull($syno->authenticator());
    }

    public function test_credentials_log_in_lazily_on_the_first_call(): void
    {
        $http = FakePsrClient::sequence(self::LOGIN_OK, self::CALL_OK);

        $syno = Synology::to(self::URL)
            ->credentials('user', 'pass', otpCode: '123456')
            ->rememberDevice()
            ->http($http, new Psr17Factory)
            ->connect();

        // 만드는 것만으로는 네트워크를 때리지 않는다.
        $this->assertSame([], $http->requests);

        $syno->contacts->info->get_timezone();

        $this->assertSame(['SYNO.API.Auth', 'SYNO.Contacts.Info'], $http->calledApis());
        $this->assertSame('123456', FakePsrClient::paramsOf($http->requests[0])['otp_code']);
        $this->assertSame('yes', FakePsrClient::paramsOf($http->requests[0])['enable_device_token']);
        $this->assertInstanceOf(Authenticator::class, $syno->authenticator());
    }

    /**
     * 세션 소스를 하나도 안 줘도 만들어져야 한다. `const AUTH = false` 인 API 는
     * 세션이 필요 없고, 그 호출만 하는 클라이언트가 실제로 있다(Chat 봇 토큰).
     */
    public function test_it_builds_without_any_session_source(): void
    {
        $http = new FakePsrClient;

        $syno = Synology::to(self::URL)->http($http, new Psr17Factory)->connect();
        $syno->chat->external->incoming('BOT_TOKEN', '안녕하세요');

        $this->assertSame(['SYNO.Chat.External'], $http->calledApis());
        $this->assertArrayNotHasKey('_sid', $http->lastParams());
    }

    // --- onLogin: 콜드 스타트 로그인 ------------------------------------------

    /**
     * 잠금을 잡고 보니 다른 프로세스가 이미 로그인해 뒀다면, 기본 클로저를 부르지 않고
     * 그 세션을 쓸 수 있어야 한다. 캐시가 비는 순간 워커 수만큼 로그인이 나가는 걸
     * 막을 수 있는 자리는 여기뿐이다 — 만료 재시도는 세션이 있었던 경우만 탄다.
     */
    public function test_on_login_can_answer_without_logging_in(): void
    {
        $http = new FakePsrClient;
        $shared = new InMemoryStore;

        $syno = Synology::to(self::URL)
            ->credentials('user', 'pass')
            ->store($shared)
            ->onLogin(function (Closure $login) use ($shared): Session {
                // 잠금을 잡는 사이 다른 프로세스가 넣어 뒀다고 치자.
                $shared->put(new Session('SOMEONE_ELSES_SID'));

                return $shared->get() ?? $login();
            })
            ->http($http, new Psr17Factory)
            ->connect();

        $syno->contacts->info->get_timezone();

        $this->assertSame(['SYNO.Contacts.Info'], $http->calledApis());
        $this->assertSame('SOMEONE_ELSES_SID', $http->lastBody()['_sid']);
    }

    public function test_on_login_wraps_the_real_login_when_the_store_is_still_empty(): void
    {
        $http = FakePsrClient::sequence(self::LOGIN_OK, self::CALL_OK);
        $calls = 0;

        $syno = Synology::to(self::URL)
            ->credentials('user', 'pass')
            ->onLogin(function (Closure $login) use (&$calls): Session {
                $calls++;

                return $login();
            })
            ->http($http, new Psr17Factory)
            ->connect();

        $syno->contacts->info->get_timezone();

        $this->assertSame(1, $calls);
        $this->assertSame(['SYNO.API.Auth', 'SYNO.Contacts.Info'], $http->calledApis());
        $this->assertSame('NEW_SID', $http->lastBody()['_sid']);
    }

    /**
     * 로그인할 것이 없으면 콜백은 영원히 불리지 않는다. 조용히 무시하면 소비자는
     * 잠갔다고 믿는데 실제로는 아무것도 안 잠긴 상태가 된다.
     */
    public function test_on_login_without_credentials_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Synology::to(self::URL)
            ->session('SID')
            ->onLogin(static fn (Closure $login): Session => $login())
            ->http(new FakePsrClient, new Psr17Factory)
            ->connect();
    }

    // --- onSessionExpired: 만료 재시도 ----------------------------------------

    public function test_the_expiry_callback_gets_the_stale_sid_and_the_authenticator(): void
    {
        $http = FakePsrClient::sequence(
            self::LOGIN_OK,
            '{"success":false,"error":{"code":106}}',
            self::LOGIN_OK,
            self::CALL_OK,
        );

        $seen = [];
        $syno = Synology::to(self::URL)
            ->credentials('user', 'pass')
            ->onSessionExpired(function (string $staleSid, ?Authenticator $auth) use (&$seen): Session {
                $seen = [$staleSid, $auth];

                return $auth->refresh();
            })
            ->http($http, new Psr17Factory)
            ->connect();

        $syno->contacts->info->get_timezone();

        $this->assertSame('NEW_SID', $seen[0]);
        $this->assertSame($syno->authenticator(), $seen[1]);
        $this->assertSame(
            ['SYNO.API.Auth', 'SYNO.Contacts.Info', 'SYNO.API.Auth', 'SYNO.Contacts.Info'],
            $http->calledApis(),
        );
    }

    /**
     * 자격증명 없이 저장소만 주는 소비자도 이제 만료를 잡을 수 있다.
     * 전에는 `Http\Connection` 으로 좁혀야만 콜백을 걸 수 있었다.
     */
    public function test_a_store_only_client_can_handle_expiry_itself(): void
    {
        $http = FakePsrClient::sequence('{"success":false,"error":{"code":107}}', self::CALL_OK);
        $shared = new InMemoryStore(new Session('OLD_SID'));

        $syno = Synology::to(self::URL)
            ->store($shared)
            ->onSessionExpired(function (string $staleSid, ?Authenticator $auth) use ($shared): Session {
                $this->assertSame('OLD_SID', $staleSid);
                $this->assertNull($auth);       // 자격증명이 없으니 없다

                $shared->put($fresh = new Session('REFRESHED_BY_CONSUMER'));

                return $fresh;
            })
            ->http($http, new Psr17Factory)
            ->connect();

        $syno->contacts->info->get_timezone();

        $this->assertSame('REFRESHED_BY_CONSUMER', $http->lastBody()['_sid']);
    }

    /**
     * 두 자리가 한 잠금 아래 모이는 게 이 빌더의 요점이다. 요청이 나간 뒤 다른
     * 프로세스가 저장소를 갈아끼운 상황에서, 재로그인 없이 그 세션으로 수렴해야 한다.
     */
    public function test_both_seams_can_be_serialized_by_the_consumer(): void
    {
        $shared = new InMemoryStore;
        $logins = 0;

        // 첫 요청은 만료로 실패하고, 그 사이 다른 프로세스가 세션을 갈아끼운다.
        $http = new class($shared) extends FakePsrClient
        {
            public function __construct(private readonly InMemoryStore $shared)
            {
                parent::__construct();
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->requests[] = $request;
                $api = FakePsrClient::paramsOf($request)['api'];
                $nth = count(array_filter($this->calledApis(), static fn (string $c): bool => $c === $api));

                // 첫 Contacts 호출은 만료로 실패하고, 그 순간 다른 프로세스가 저장소를 갈아끼운다.
                if ($api === 'SYNO.Contacts.Info' && $nth === 1) {
                    $this->shared->put(new Session('SID_FROM_ANOTHER_PROCESS'));

                    return self::body('{"success":false,"error":{"code":106}}');
                }

                return self::body($api === 'SYNO.API.Auth' ? ConnectorTest::LOGIN_OK : ConnectorTest::CALL_OK);
            }

            private static function body(string $json): ResponseInterface
            {
                return new PsrResponse(200, ['Content-Type' => 'application/json'], $json);
            }
        };

        $lock = static fn (Closure $work) => $work();      // 실제로는 Redis 잠금

        $syno = Synology::to(self::URL)
            ->credentials('user', 'pass')
            ->store($shared)
            // 화살표 함수는 값으로 캡처하므로 $logins 는 바깥 함수로 받는다.
            ->onLogin(function (Closure $login) use ($lock, $shared, &$logins): Session {
                return $lock(function () use ($shared, $login, &$logins): Session {
                    $logins++;

                    return $shared->get() ?? $login();
                });
            })
            ->onSessionExpired(fn (string $stale, ?Authenticator $auth): Session => $lock(
                fn (): Session => ($current = $shared->get())?->sid !== $stale ? $current : $auth->refresh(),
            ))
            ->http($http, new Psr17Factory)
            ->connect();

        $syno->contacts->info->get_timezone();

        // 로그인은 한 번도 나가지 않았다. 콜드 스타트는 잠금 안에서 한 번만 시도됐고,
        // 만료는 다른 프로세스의 세션으로 수렴했다.
        $this->assertSame(1, $logins);
        $this->assertSame(
            ['SYNO.API.Auth', 'SYNO.Contacts.Info', 'SYNO.Contacts.Info'],
            $http->calledApis(),
        );
        $this->assertSame('SID_FROM_ANOTHER_PROCESS', $http->lastBody()['_sid']);
    }
}
