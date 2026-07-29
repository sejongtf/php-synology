<?php

declare(strict_types=1);

namespace Sejongtf\Synology;

use InvalidArgumentException;
use Psr\Http\Client\ClientInterface as PsrClient;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Sejongtf\Synology\Auth\AuthenticatingStore;
use Sejongtf\Synology\Auth\Authenticator;
use Sejongtf\Synology\Auth\Credentials;
use Sejongtf\Synology\Auth\InMemoryStore;
use Sejongtf\Synology\Auth\Session;
use Sejongtf\Synology\Contracts\Connection as ConnectionContract;
use Sejongtf\Synology\Contracts\SessionStore;
use Sejongtf\Synology\Http\Connection;
use Sejongtf\Synology\Http\Endpoint;
use Sejongtf\Synology\Registry\ApiRegistry;
use Sejongtf\Synology\Services\Api\Info;

/**
 * 진입점.
 *
 * 세션을 얻는 방법이 셋이고 **셋 다 동등하다**. 로그인은 그중 하나일 뿐이지 유일한 경로가 아니다.
 *
 * ```php
 * // (A) 이미 갖고 있는 sid 를 그대로 쓴다. 로그인하지 않는다.
 * $syno = Synology::withSession('https://nas:5001', $sid);
 *
 * // (B) 외부 저장소에서 읽고 쓴다.
 * $syno = Synology::withStore($url, new CallableStore(
 *     get: fn () => $cache->get('syno.session'),
 *     put: fn (Session $s) => $cache->set('syno.session', $s->toArray()),
 * ));
 *
 * // (C) 자격증명으로 로그인한다. 실제 로그인은 첫 요청 때 일어난다.
 * $syno = Synology::connect($url, 'user', 'pass', otpCode: '123456');
 * ```
 *
 * @property \Sejongtf\Synology\Services\Chat\Chat $chat
 * @property \Sejongtf\Synology\Services\Calendar\Calendar $calendar
 * @property \Sejongtf\Synology\Services\Contacts\Contacts $contacts
 * @property \Sejongtf\Synology\Services\Core\Core $core
 * @property \Sejongtf\Synology\Services\MailPlusServer\MailPlusServer $mail_plus_server
 * @property \Sejongtf\Synology\Services\Personal\Application $application
 * @property \Sejongtf\Synology\Services\Personal\MailAccount $mail_account
 * @property \Sejongtf\Synology\Services\Personal\Notification $notification
 * @property \Sejongtf\Synology\Services\Personal\Profile $profile
 */
final class Synology
{
    /** @var array<string, class-string<Service>> */
    private const SERVICES = [
        'chat' => Services\Chat\Chat::class,
        'calendar' => Services\Calendar\Calendar::class,
        'contacts' => Services\Contacts\Contacts::class,
        'core' => Services\Core\Core::class,
        'mail_plus_server' => Services\MailPlusServer\MailPlusServer::class,
        'application' => Services\Personal\Application::class,
        'mail_account' => Services\Personal\MailAccount::class,
        'notification' => Services\Personal\Notification::class,
        'profile' => Services\Personal\Profile::class,
    ];

    /** @var array<string, Service> */
    private array $resolved = [];

    private ?Authenticator $authenticator = null;

    public function __construct(private readonly ConnectionContract $connection) {}

    /**
     * 이미 갖고 있는 세션으로 시작한다. 로그인하지 않는다.
     *
     * @param  Session|string  $session  `Session` 이거나 sid 문자열
     */
    public static function withSession(
        string $url,
        Session|string $session,
        ?PsrClient $http = null,
        ?RequestFactoryInterface $requests = null,
        ?StreamFactoryInterface $streams = null,
    ): self {
        $session = is_string($session) ? new Session($session) : $session;

        return self::withStore($url, new InMemoryStore($session), $http, $requests, $streams);
    }

    /**
     * 세션을 외부 저장소에서 읽고 쓴다.
     */
    public static function withStore(
        string $url,
        SessionStore $store,
        ?PsrClient $http = null,
        ?RequestFactoryInterface $requests = null,
        ?StreamFactoryInterface $streams = null,
    ): self {
        $requests = Connection::requestFactory($requests);

        return new self(new Connection(
            Connection::psrClient($http),
            $requests,
            Connection::streamFactory($streams, $requests),
            new Endpoint($url),
            $store,
        ));
    }

    /**
     * 자격증명으로 로그인한다.
     *
     * 실제 로그인은 **첫 요청 때** 일어난다. 생성 시점에 네트워크를 때리면
     * 서비스 컨테이너 부팅 중에 로그인이 발생해 곤란해진다.
     *
     * @param  SessionStore|null  $store  세션을 둘 곳. 생략하면 프로세스 메모리.
     */
    public static function connect(
        string $url,
        string $account,
        string $passwd,
        ?string $session = null,
        ?string $otpCode = null,
        ?string $deviceId = null,
        ?string $deviceName = null,
        bool $rememberDevice = false,
        ?SessionStore $store = null,
        ?PsrClient $http = null,
        ?RequestFactoryInterface $requests = null,
        ?StreamFactoryInterface $streams = null,
    ): self {
        $store ??= new InMemoryStore;
        $credentials = new Credentials(
            $account, $passwd, $session, $otpCode, $deviceId, $deviceName, $rememberDevice,
        );

        // 로그인 자체는 아직 하지 않는다. 데코레이터가 세션이 빌 때 대신 불러 준다.
        $authenticator = null;
        $lazy = new AuthenticatingStore($store, function () use (&$authenticator) {
            return $authenticator->login();
        });

        $requests = Connection::requestFactory($requests);

        $connection = new Connection(
            Connection::psrClient($http),
            $requests,
            Connection::streamFactory($streams, $requests),
            new Endpoint($url),
            $lazy,
        );

        // Authenticator 는 데코레이터가 아니라 안쪽 저장소를 쓴다(재귀 방지).
        $authenticator = new Authenticator($connection, $store, $credentials);

        // 자격증명이 있으므로 세션 만료(106/107/119) 시 한 번 다시 로그인할 수 있다.
        // sid 만 주입한 경로에는 이 콜백이 없고, 따라서 재시도도 없다.
        $connection->onSessionExpired(static fn (): Session => $authenticator->refresh());

        $synology = new self($connection);
        $synology->authenticator = $authenticator;

        return $synology;
    }

    public function connection(): ConnectionContract
    {
        return $this->connection;
    }

    /**
     * `connect()` 로 만들었을 때만 있다. sid 를 직접 주입한 경우에는 null.
     */
    public function authenticator(): ?Authenticator
    {
        return $this->authenticator;
    }

    /**
     * `SYNO.API.Info` 를 조회해 경로와 버전 정보를 연결에 반영한다.
     *
     * 하지 않아도 동작한다 — 그 경우 전부 `entry.cgi` 로 간다.
     *
     * @param  string|array|null  $query  조회할 API 이름. 생략하면 전체.
     */
    public function discover($query = null): ApiRegistry
    {
        $data = (new Info($this->connection))->query($query);
        $registry = ApiRegistry::fromInfoResponse($data ?? []);

        if ($this->connection instanceof Connection) {
            $this->connection->useRegistry($this->connection->getRegistry()->merge($registry));
        }

        return $registry;
    }

    public function logout(): void
    {
        $this->authenticator?->logout();
    }

    public function hasService(string $name): bool
    {
        return isset(self::SERVICES[self::normalize($name)]);
    }

    public function service(string $name): Service
    {
        $key = self::normalize($name);

        if (! isset(self::SERVICES[$key])) {
            throw new InvalidArgumentException("알 수 없는 서비스입니다: [{$name}]");
        }

        return $this->resolved[$key] ??= new (self::SERVICES[$key])($this->connection);
    }

    public function __get(string $name): Service
    {
        return $this->service($name);
    }

    public function __isset(string $name): bool
    {
        return $this->hasService($name);
    }

    /**
     * `fileStation` 과 `file_station` 을 모두 받는다.
     *
     * 패키지 규칙은 DSM 을 따른 snake_case 지만, PHP 쪽에서는 camelCase 가 자연스럽다.
     */
    private static function normalize(string $name): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
    }
}
