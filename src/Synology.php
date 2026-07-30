<?php

declare(strict_types=1);

namespace Sejongtf\Synology;

use InvalidArgumentException;
use Psr\Http\Client\ClientInterface as PsrClient;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Sejongtf\Synology\Auth\Authenticator;
use Sejongtf\Synology\Auth\Session;
use Sejongtf\Synology\Contracts\Connection as ConnectionContract;
use Sejongtf\Synology\Contracts\SessionStore;
use Sejongtf\Synology\Http\Connection;
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
 * 셋 다 `to()` 가 돌려주는 `Connector` 로 조립된다. 로그인 자리를 잠그거나(`onLogin()`)
 * 만료 재시도를 갈아끼우는(`onSessionExpired()`) 등 손댈 게 있으면 그쪽을 직접 쓴다.
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

    public function __construct(
        private readonly ConnectionContract $connection,
        private readonly ?Authenticator $authenticator = null,
    ) {}

    /**
     * 조립을 시작한다. 아래 정적 메서드들로 모자랄 때 쓴다.
     *
     * ```php
     * $syno = Synology::to($url)->credentials('user', 'pass')->store($shared)->connect();
     * ```
     */
    public static function to(string $url): Connector
    {
        return Connector::to($url);
    }

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
        return self::to($url)
            ->session($session)
            ->http($http, $requests, $streams)
            ->connect();
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
        return self::to($url)
            ->store($store)
            ->http($http, $requests, $streams)
            ->connect();
    }

    /**
     * 자격증명으로 로그인한다.
     *
     * 실제 로그인은 **첫 요청 때** 일어난다. 생성 시점에 네트워크를 때리면
     * 서비스 컨테이너 부팅 중에 로그인이 발생해 곤란해진다.
     *
     * @param  SessionStore|null  $store  세션을 둘 곳. 생략하면 프로세스 메모리.
     *                                    **여러 프로세스가 공유하는 저장소라면 이 메서드로는
     *                                    부족하다.** 로그인이 겹칠 수 있는 자리가 둘인데
     *                                    (세션이 빌 때, 만료돼 다시 받을 때) 여기서는 둘 다
     *                                    기본값으로 고정된다. `to($url)` 로 조립하면서
     *                                    `Connector::onLogin()`/`onSessionExpired()` 를
     *                                    쓰면 그 두 자리를 소비자 잠금으로 감쌀 수 있다.
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
        $connector = self::to($url)
            ->credentials($account, $passwd, $otpCode, $session, $deviceId, $deviceName, $rememberDevice)
            ->http($http, $requests, $streams);

        if ($store !== null) {
            $connector->store($store);
        }

        return $connector->connect();
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
