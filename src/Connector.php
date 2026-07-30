<?php

declare(strict_types=1);

namespace Sejongtf\Synology;

use Closure;
use InvalidArgumentException;
use Psr\Http\Client\ClientInterface as PsrClient;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Sejongtf\Synology\Auth\AuthenticatingStore;
use Sejongtf\Synology\Auth\Authenticator;
use Sejongtf\Synology\Auth\Credentials;
use Sejongtf\Synology\Auth\InMemoryStore;
use Sejongtf\Synology\Auth\Session;
use Sejongtf\Synology\Contracts\SessionStore;
use Sejongtf\Synology\Http\Connection;
use Sejongtf\Synology\Http\Endpoint;

/**
 * 클라이언트를 조립한다. `Synology::to($url)` 로 시작한다.
 *
 * ```php
 * $syno = Synology::to('https://nas:5001')
 *     ->credentials('user', 'pass', otpCode: '123456')
 *     ->store($shared)
 *     ->connect();
 * ```
 *
 * **배선은 여기 한 군데에만 있다.** `Synology::withSession()`/`withStore()`/`connect()` 도
 * 전부 이 클래스를 거친다 — 짧은 경로를 없앨 이유는 없지만, 재귀를 막는 조립 순서(아래
 * `connect()` 참고)가 네 벌로 갈라지면 그중 하나는 반드시 틀어진다.
 *
 * 세션 소스 셋(`session()`/`store()`/`credentials()`)은 서로 배타적이지 않다. 공유
 * 저장소를 쓰면서 로그인도 맡기는 조합이 오히려 흔하다. 셋 다 생략해도 만들어진다 —
 * `const AUTH = false` 인 API(Chat 봇 토큰, 로그인 전 `SYNO.API.Info`)는 세션이 필요 없다.
 *
 * 상태를 쌓아 두었다가 `connect()` 에서 한 번에 만든다. 메서드는 `$this` 를 돌려주므로
 * 같은 빌더를 두 번 `connect()` 하면 같은 설정으로 **다른** 클라이언트가 나온다.
 */
final class Connector
{
    private ?PsrClient $http = null;

    private ?RequestFactoryInterface $requests = null;

    private ?StreamFactoryInterface $streams = null;

    private ?SessionStore $store = null;

    private ?string $account = null;

    private ?string $passwd = null;

    private ?string $sessionName = null;

    private ?string $otpCode = null;

    private ?string $deviceId = null;

    private ?string $deviceName = null;

    private bool $rememberDevice = false;

    /** @var (Closure(Closure(): Session): Session)|null */
    private ?Closure $login = null;

    /** @var (Closure(string, ?Authenticator): Session)|null */
    private ?Closure $sessionExpired = null;

    private function __construct(private readonly string $url) {}

    public static function to(string $url): self
    {
        return new self($url);
    }

    /**
     * 이미 갖고 있는 세션으로 시작한다. 로그인하지 않는다.
     *
     * @param  Session|string  $session  `Session` 이거나 sid 문자열
     */
    public function session(Session|string $session): self
    {
        return $this->store(new InMemoryStore(
            is_string($session) ? new Session($session) : $session,
        ));
    }

    /**
     * 세션을 읽고 쓸 저장소. 생략하면 프로세스 메모리.
     */
    public function store(SessionStore $store): self
    {
        $this->store = $store;

        return $this;
    }

    /**
     * 자격증명. 실제 로그인은 세션이 필요한 **첫 요청 때** 일어난다.
     *
     * @param  string|null  $sessionName  DSM 의 `session` 파라미터(세션을 쓰는 응용 이름).
     *                                    위 `session()` 과 이름만 닮았을 뿐 다른 것이다.
     */
    public function credentials(
        string $account,
        string $passwd,
        ?string $otpCode = null,
        ?string $sessionName = null,
        ?string $deviceId = null,
        ?string $deviceName = null,
        bool $rememberDevice = false,
    ): self {
        $this->account = $account;
        $this->passwd = $passwd;
        $this->otpCode = $otpCode;
        $this->sessionName = $sessionName;
        $this->deviceId = $deviceId;
        $this->deviceName = $deviceName;
        $this->rememberDevice = $rememberDevice;

        return $this;
    }

    /**
     * 로그인에 device token 을 받아 둔다. 2단계 인증이 강제된 계정을 자동으로
     * 재로그인하려면 필요하다.
     */
    public function rememberDevice(bool $remember = true): self
    {
        $this->rememberDevice = $remember;

        return $this;
    }

    /**
     * PSR 구현체를 직접 준다. 생략하면 `php-http/discovery` 로 찾는다.
     *
     * 대부분의 구현체는 한 클래스가 요청 팩토리와 스트림 팩토리를 겸하므로 보통 둘이면 된다.
     */
    public function http(
        ?PsrClient $http = null,
        ?RequestFactoryInterface $requests = null,
        ?StreamFactoryInterface $streams = null,
    ): self {
        $this->http = $http ?? $this->http;
        $this->requests = $requests ?? $this->requests;
        $this->streams = $streams ?? $this->streams;

        return $this;
    }

    /**
     * 세션이 비어 있어 처음 로그인하는 자리를 감싼다.
     *
     * 콜백은 **기본 로그인 클로저**를 받아 세션을 돌려준다. 부르지 않고 다른 세션을
     * 돌려줘도 된다 — 잠금을 잡고 보니 그 사이 다른 프로세스가 로그인해 둔 경우다.
     *
     * ```php
     * ->onLogin(fn (Closure $login) => $lock->block(5, fn () => $shared->get() ?? $login()))
     * ```
     *
     * 저장소를 여러 프로세스가 공유할 때만 의미가 있다. 캐시가 비는 순간(TTL 만료, 배포,
     * 캐시 서버 재시작) 워커 수만큼 로그인이 동시에 나가고, DSM 은 같은 계정의 뒤 로그인이
     * 앞 세션을 끊으므로 서로가 서로를 무효화한다. 만료 재시도(`onSessionExpired()`)로는
     * 이 첫 물결을 막을 수 없다 — 그쪽은 이미 세션이 있었다가 죽은 경우만 탄다.
     *
     * **직접 만든 저장소의 `get()` 안에서 로그인하는 것으로 대신하지 말 것.**
     * `Authenticator::refresh()` 가 device token 을 챙기려고 `get()` 을 부르므로,
     * 재인증 한 번에 로그인이 두 번 나간다.
     *
     * @param  Closure(Closure(): Session): Session  $login
     */
    public function onLogin(Closure $login): self
    {
        $this->login = $login;

        return $this;
    }

    /**
     * 세션 만료(106/107/119)를 만났을 때 무엇을 할지.
     *
     * 콜백은 **방금 실패한 요청에 실려 있던 sid** 와, 자격증명을 줬다면 `Authenticator` 를
     * 받는다. 저장소의 현재 sid 가 넘어온 값과 다르면 그 사이 다른 프로세스가 이미
     * 갱신한 것이니 다시 로그인할 이유가 없다.
     *
     * ```php
     * ->onSessionExpired(fn (string $staleSid, Authenticator $auth) => $lock->block(5, function () use ($staleSid, $auth) {
     *     $current = $shared->get();
     *
     *     return $current && $current->sid !== $staleSid ? $current : $auth->refresh();
     * }))
     * ```
     *
     * 두 번째 인자가 있는 건 `Authenticator` 가 클라이언트를 다 만든 뒤에야 생기기
     * 때문이다. 쓰지 않을 거면 인자 하나만 받아도 된다.
     *
     * 생략하면 자격증명이 있을 때 `Authenticator::refresh()` 를 그냥 부른다. 자격증명이
     * 없으면(sid 주입) 재시도 자체가 없다 — 라이브러리가 다시 로그인할 방법이 없으므로
     * 오류가 그대로 올라간다. 그 경우에도 이 콜백을 주면 소비자가 직접 갱신할 수 있다.
     *
     * @param  Closure(string, ?Authenticator): Session  $callback
     */
    public function onSessionExpired(Closure $callback): self
    {
        $this->sessionExpired = $callback;

        return $this;
    }

    /**
     * 클라이언트를 만든다. 네트워크를 때리지 않는다.
     *
     * 조립 순서에 재귀를 막는 두 가지가 들어 있다. 둘 다 CLAUDE.md 에 적혀 있고, 여기
     * 말고 다른 데서 이 구조를 다시 짜지 않는 이유가 그것이다.
     *
     * - `AuthenticatingStore` 는 연결에만 넘긴다. 세션이 비면 그때 로그인하는 저장소라,
     *   생성 시점에 네트워크를 때리지 않으면서 첫 요청에 로그인이 붙는다.
     * - `Authenticator` 에는 **안쪽 저장소**를 준다. 데코레이터를 주면 로그인 응답을
     *   저장하려다 다시 로그인하는 무한 재귀가 된다.
     *
     * @throws InvalidArgumentException `onLogin()` 을 줬는데 자격증명이 없는 경우.
     *                                  로그인할 것이 없으니 그 콜백은 영원히 불리지 않고,
     *                                  조용히 무시하면 막으려던 로그인 폭주가 그대로 난다.
     */
    public function connect(): Synology
    {
        $credentials = $this->credentialsOrNull();

        if ($credentials === null && $this->login !== null) {
            throw new InvalidArgumentException(
                'onLogin() 은 credentials() 와 같이 써야 합니다. 자격증명이 없으면 이 라이브러리가 로그인하지 않으므로 콜백이 불릴 일이 없습니다.',
            );
        }

        $store = $this->store ?? new InMemoryStore;
        $authenticator = null;

        $requests = Connection::requestFactory($this->requests);

        $connection = new Connection(
            Connection::psrClient($this->http),
            $requests,
            Connection::streamFactory($this->streams, $requests),
            new Endpoint($this->url),
            // 자격증명이 없으면 지연 로그인도 없다. 저장소를 그대로 쓴다.
            $credentials === null ? $store : new AuthenticatingStore($store, $this->lazyLogin($authenticator)),
        );

        if ($credentials !== null) {
            // 데코레이터가 아니라 안쪽 저장소다(재귀 방지).
            $authenticator = new Authenticator($connection, $store, $credentials);
        }

        if ($this->sessionExpired !== null) {
            $callback = $this->sessionExpired;
            $connection->onSessionExpired(
                static fn (string $staleSid): Session => $callback($staleSid, $authenticator),
            );
        } elseif ($authenticator !== null) {
            $connection->onSessionExpired(static fn (): Session => $authenticator->refresh());
        }

        return new Synology($connection, $authenticator);
    }

    /**
     * 지연 로그인 클로저. `onLogin()` 을 줬으면 그걸로 감싼다.
     *
     * `$authenticator` 를 **참조로** 받는다 — 이 클로저는 연결을 만들 때 필요하고
     * `Authenticator` 는 그 연결이 있어야 만들어지므로, 지금은 아직 null 이다.
     *
     * @return Closure(): Session
     */
    private function lazyLogin(?Authenticator &$authenticator): Closure
    {
        $default = static function () use (&$authenticator): Session {
            return $authenticator->login();
        };

        if ($this->login === null) {
            return $default;
        }

        $wrap = $this->login;

        return static fn (): Session => $wrap($default);
    }

    private function credentialsOrNull(): ?Credentials
    {
        if ($this->account === null || $this->passwd === null) {
            return null;
        }

        return new Credentials(
            $this->account,
            $this->passwd,
            $this->sessionName,
            $this->otpCode,
            $this->deviceId,
            $this->deviceName,
            $this->rememberDevice,
        );
    }
}
