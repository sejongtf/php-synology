<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Http;

use Closure;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface as PsrClient;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface as PsrRequest;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;
use Sejongtf\Synology\Auth\Session;
use Sejongtf\Synology\Contracts\Connection as ConnectionContract;
use Sejongtf\Synology\Contracts\SessionStore;
use Sejongtf\Synology\Exceptions\TransportException;
use Sejongtf\Synology\Message\Response;
use Sejongtf\Synology\Registry\ApiRegistry;
use Sejongtf\Synology\Services\Api\Auth;

/**
 * PSR-18 위에 올린 기본 연결.
 *
 * PSR-18 구현체는 소비자가 고른다 — 이 패키지는 PSR 인터페이스만 요구한다.
 * 아래쪽 정적 메서드들이 그 구현체를 찾아 주고, 그 결과가 이 클래스의 생성자 인자가 된다.
 *
 * 인증에 대한 역할 분담이 중요하다:
 *
 * - **`_sid` 는 이 클래스가 붙이지 않는다.** `Api::raw()` 가 붙인다.
 *   `const AUTH = false` 인 API(`SYNO.Chat.External` 의 봇 토큰 인증, `SYNO.API.Info` 의
 *   로그인 이전 조회)가 있어서, 세션이 있다고 무조건 붙이면 그 의도가 깨진다.
 *   이 클래스는 세션이 어디 있는지만 알려 준다(`getSessionId()`).
 * - **`SynoToken` 은 이 클래스가 붙인다.** 단, `_sid` 가 이미 실린 요청에만 붙인다.
 *   CSRF 토큰은 세션 인증과 짝이므로 세션 없는 요청에 붙일 이유가 없다.
 *
 * 요청은 **POST 로 나가고 요청 내용은 전부 form body 에 담는다.** `SynoToken` 하나만
 * 쿼리스트링에 남고, 그것도 세션에 토큰이 있을 때뿐이라 URL 은 보통 이 꼴이다:
 *
 *     POST /webapi/entry.cgi
 *
 * GET + 쿼리스트링을 버린 이유가 셋이다:
 *
 * 1. `account`/`passwd`/`otp_code` 와 `_sid` 가 URL 에서 빠진다. 예전에는 로그인까지
 *    GET 으로 나가 NAS access log 와 리버스 프록시 로그에 평문으로 남았다.
 * 2. URL 길이 한계가 없어진다. 실측한 414 경계는 약 8,100 bytes 였고, 채팅 메시지나
 *    캘린더 본문 JSON 은 그걸 넘길 수 있다.
 * 3. GET 은 멱등으로 취급돼 클라이언트나 프록시가 연결 실패 시 임의로 재시도한다.
 *    `create` 계열이 GET 으로 나가면 중복 실행 위험이 있다.
 *
 * `SynoToken` 만 예외인 건 취향이 아니라 DSM 의 제약이다 — **쿼리스트링이나
 * `X-SYNO-TOKEN` 헤더로만 읽히고**, 본문에 실으면 CSRF 검사가 못 보고 119 로
 * 떨어진다(실기기 확인).
 *
 * 대신 access log 에는 어느 API 를 불렀는지 남지 않는다. PHP 쪽 디버깅은 영향이 없다 —
 * `TransportException` 메시지에 `[api::method]` 가 들어가고 `RequestException` 은
 * PSR-7 응답을 들고 있다.
 */
final class Connection implements ConnectionContract
{
    /**
     * 본문이 아니라 쿼리스트링으로 나가는 키. 나머지는 전부 본문이다.
     *
     * `dispatch()` 가 요청을 이 목록으로 가른다. 여기 든 키가 하나도 없으면
     * URL 에 물음표조차 붙지 않는다.
     */
    private const QUERY_KEYS = ['SynoToken'];

    /**
     * 세션이 끝났다는 뜻의 공통 코드.
     *
     * 106 = Session timeout, 107 = Session interrupted by duplicated login,
     * 119 = SID not found. 107 은 같은 계정으로 다른 곳에서 로그인해 세션이 끊긴 것이라
     * 나머지 둘과 대처가 같다 — 다시 로그인하면 된다.
     */
    private const SESSION_EXPIRED_CODES = [106, 107, 119];

    /** @var (Closure(string): Session)|null */
    private ?Closure $reauthenticate = null;

    public function __construct(
        private readonly PsrClient $http,
        private readonly RequestFactoryInterface $requests,
        private readonly StreamFactoryInterface $streams,
        private readonly Endpoint $endpoint,
        private readonly SessionStore $sessions,
        private ApiRegistry $registry = new ApiRegistry,
    ) {}

    /**
     * 세션 만료(106/107/119) 시 다시 로그인할 방법을 알려 준다.
     *
     * 설정하면 만료된 요청을 **한 번만** 재시도한다. 설정하지 않으면 재시도하지 않는다 —
     * 자격증명 없이 sid 만 주입받은 경우 라이브러리가 다시 로그인할 방법이 없기 때문이다.
     *
     * 콜백은 **방금 실패한 요청에 실려 있던 sid** 를 받는다. 저장소를 여러 프로세스가
     * 공유할 때, 그 값과 저장소의 현재 sid 를 비교하면 다시 로그인해야 하는지 알 수 있다.
     * 다르면 그 사이 다른 프로세스가 이미 갱신한 것이니 그 세션을 그대로 돌려주면 된다.
     * 이 비교는 여기서 대신 해 줄 수 없다 — 저장소가 원자적인지, 잠금이 있는지는
     * 저장소를 만든 쪽만 안다. 만료된 sid 는 이 클래스만 알고 있으므로 넘겨만 준다.
     *
     * ```php
     * $connection->onSessionExpired(fn (string $staleSid): Session => $lock->block(5, function () use ($staleSid, $store, $authenticator) {
     *     $current = $store->get();
     *
     *     // 다른 프로세스가 이미 갈아끼웠다. 또 로그인하면 그쪽 세션을 107 로 끊는다.
     *     return $current && $current->sid !== $staleSid ? $current : $authenticator->refresh();
     * }));
     * ```
     *
     * **콜백 안에서 `$store->forget()` 을 먼저 부르면 안 된다.** 버리는 순서는
     * `Authenticator::refresh()` 가 지킨다 — 그쪽은 저장소에 남은 세션에서 device token
     * 을 챙긴 **다음에** 버린다. 미리 비워 두면 그 조회가 빈 저장소를 읽고, 2단계 인증이
     * 강제된 계정에서 재로그인이 OTP 를 요구해 실패한다.
     *
     * @param  Closure(string): Session  $reauthenticate  만료된 sid 를 받아 쓸 수 있는
     *                                                    세션을 돌려주는 콜백
     */
    public function onSessionExpired(Closure $reauthenticate): self
    {
        $this->reauthenticate = $reauthenticate;

        return $this;
    }

    public function request(string $api, int $version, string $method, array $params = []): Response
    {
        $request = [
            'api' => $api,
            'version' => $version,
            'method' => $method,
        ] + $this->prepare($params);

        // CSRF 토큰은 세션 인증 요청에만 붙인다.
        //
        // **단락 평가가 load-bearing 이다.** `isset($request['_sid'])` 를 먼저 보지 않고
        // `sessions->get()` 을 위로 끌어올리면, connect() 가 씌운 AuthenticatingStore 가
        // 세션이 없을 때 로그인을 시도하고 → Auth::login() 이 이 메서드를 다시 부르고 →
        // 아직 저장 전이라 또 로그인하는 **무한 재귀**가 된다.
        // SynologyTest::test_a_bot_token_api_never_triggers_the_lazy_login 이 이걸 막는다.
        if (isset($request['_sid']) && ($session = $this->sessions->get())?->hasSynoToken()) {
            $request['SynoToken'] ??= $session->synoToken;
        }

        $response = $this->dispatch($api, $method, $request);

        if (! $this->shouldReauthenticate($api, $request, $response)) {
            return $response;
        }

        return $this->dispatch($api, $method, $this->reissue($request));
    }

    /**
     * 세션이 만료됐으니 다시 로그인하고 한 번 더 시도할지.
     *
     * 자격증명이 없으면(`onSessionExpired` 미설정) 재시도하지 않는다. sid 를 외부에서
     * 관리하는 소비자에게는 라이브러리가 다시 로그인할 방법 자체가 없다.
     * 그 경우 오류가 그대로 올라가고 갱신은 소비자가 한다.
     *
     * @param  array<string, mixed>  $request
     */
    private function shouldReauthenticate(string $api, array $request, Response $response): bool
    {
        return $this->reauthenticate !== null
            // 로그인 요청이 만료로 실패하는 건 말이 안 되고, 재시도하면 무한 루프다.
            && $api !== Auth::API_NAME
            // 세션을 안 쓰는 요청은 다시 로그인해도 달라질 게 없다.
            && isset($request['_sid'])
            && ! $response->success()
            && in_array($response->errorCode(), self::SESSION_EXPIRED_CODES, true);
    }

    /**
     * 새 세션으로 갈아끼운 요청.
     *
     * 콜백에 **만료된 sid** 를 넘긴다. 저장소를 공유하는 소비자가 "그 사이 다른
     * 프로세스가 이미 갱신했는지" 를 판단할 수 있는 유일한 값이고, 여기 말고는
     * 아무도 들고 있지 않다(`shouldReauthenticate()` 가 이미 존재를 보장한다).
     *
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    private function reissue(array $request): array
    {
        $session = ($this->reauthenticate)((string) $request['_sid']);

        $request['_sid'] = $session->sid;

        // 새 SynoToken 으로 바꾸되, 이번 세션에 없으면 옛것을 남기지 않는다.
        if ($session->hasSynoToken()) {
            $request['SynoToken'] = $session->synoToken;
        } else {
            unset($request['SynoToken']);
        }

        return $request;
    }

    /**
     * 요청 하나를 URL 과 본문으로 가른다.
     *
     * 본문은 절대 비지 않는다 — `api`/`version`/`method` 가 항상 들어 있기 때문이다.
     * 이건 우연이 아니라 지켜야 할 성질이다. 본문이 빈 POST 는 라우팅이 쿼리스트링에
     * 멀쩡히 있어도 DSM 이 101 로 거절한다(실기기 확인). 본문을 먼저 읽고 비어 있으면
     * 쿼리를 보기 전에 포기하는 것으로 보인다.
     *
     * @param  array<string, mixed>  $request  쿼리·본문으로 갈리기 전의 요청 전체
     */
    private function dispatch(string $api, string $method, array $request): Response
    {
        $query = array_intersect_key($request, array_flip(self::QUERY_KEYS));

        $url = $this->endpoint->url($this->registry->path($api));

        if ($query !== []) {
            $url .= '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        // PSR-17 은 새 요청의 본문 스트림이 쓰기 가능하다고 보장하지 않는다.
        // 거기에 직접 쓰지 말고 팩토리로 만들어 갈아끼워야 구현체를 가리지 않는다.
        $psr = $this->requests->createRequest('POST', $url)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($this->streams->createStream(
                http_build_query(array_diff_key($request, $query), '', '&', PHP_QUERY_RFC3986),
            ));

        return $this->sendPsr($api, $method, $psr);
    }

    private function sendPsr(string $api, string $method, PsrRequest $psr): Response
    {
        try {
            $response = $this->http->sendRequest($psr);
        } catch (ClientExceptionInterface $e) {
            throw new TransportException(
                "Synology 요청에 실패했습니다 [{$api}::{$method}]: ".$e->getMessage(),
                0,
                $e,
            );
        }

        return new Response($response, ErrorMapper::for($api));
    }

    /**
     * 세션의 sid. `Api::raw()` 가 `_sid` 를 붙일 때 쓴다.
     */
    public function getSessionId(): ?string
    {
        return $this->sessions->get()?->sid;
    }

    /**
     * NAS 의 기본 주소(`/webapi` 는 빠져 있다).
     */
    public function getEndpoint(): string
    {
        return $this->endpoint->base();
    }

    public function getSessionStore(): SessionStore
    {
        return $this->sessions;
    }

    public function getRegistry(): ApiRegistry
    {
        return $this->registry;
    }

    /**
     * 조회해 온 API 정보를 반영한다.
     */
    public function useRegistry(ApiRegistry $registry): self
    {
        $this->registry = $registry;

        return $this;
    }

    /**
     * form 인코딩에 실을 수 있는 형태로 값을 다듬는다.
     *
     * null 은 뺀다(빈 문자열로 나가면 DSM 이 값이 있는 것으로 읽는다).
     * bool 은 DSM 이 기대하는 `'true'`/`'false'` 로 바꾼다 — `http_build_query` 에
     * 그냥 넘기면 `1`/`0` 이 되어 조용히 잘못 해석된다.
     *
     * 배열 분기는 **`Api` 를 거치지 않고 이 클래스를 직접 부르는 경우를 위한 방어선**이다.
     * 보통은 `Api::raw()` 가 먼저 인코딩해서 여기 오는 값은 이미 문자열이다. 그래서
     * 플래그(`JSON_UNESCAPED_UNICODE`)를 그쪽과 맞춰 둔다 — 어느 경로로 오든 같은
     * 바이트가 나가야 한다.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function prepare(array $params): array
    {
        $prepared = [];

        foreach ($params as $key => $value) {
            if ($value === null) {
                continue;
            }

            $prepared[$key] = match (true) {
                is_bool($value) => $value ? 'true' : 'false',
                is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE),
                default => $value,
            };
        }

        return $prepared;
    }

    // --- 이 연결을 만드는 데 필요한 PSR 구현체 찾기 ---------------------------
    //
    // 이 패키지는 HTTP 구현체를 **요구하지 않는다**. PSR 인터페이스만 의존하고 구현은
    // 소비자가 고른다. 다만 매번 직접 넘기게 하면 번거로우니 `php-http/discovery` 가
    // 설치돼 있으면 그걸로 찾고, 없으면 무엇을 해야 하는지 알려 주고 멈춘다.
    //
    // 정적 메서드인 건 생성자 인자를 만드는 일이라 인스턴스가 있을 수 없기 때문이다.
    // 셋 다 인자를 주면 그대로 돌려주므로 테스트는 탐색을 타지 않는다.

    public static function psrClient(?PsrClient $client = null): PsrClient
    {
        if ($client) {
            return $client;
        }

        if (! class_exists(Psr18ClientDiscovery::class)) {
            throw new RuntimeException(self::hint('PSR-18 HTTP 클라이언트'));
        }

        return Psr18ClientDiscovery::find();
    }

    public static function requestFactory(?RequestFactoryInterface $factory = null): RequestFactoryInterface
    {
        if ($factory) {
            return $factory;
        }

        if (! class_exists(Psr17FactoryDiscovery::class)) {
            throw new RuntimeException(self::hint('PSR-17 요청 팩토리'));
        }

        return Psr17FactoryDiscovery::findRequestFactory();
    }

    /**
     * 요청 본문을 만들 스트림 팩토리.
     *
     * 파라미터를 form body 로 보내므로 반드시 필요하다. PSR-17 은 새 요청의 본문
     * 스트림이 쓰기 가능하다고 보장하지 않아서, 거기에 직접 쓰는 방식은 구현체에 따라
     * 깨진다. 팩토리로 스트림을 만들어 `withBody()` 하는 것만이 이식 가능한 방법이다.
     *
     * 대부분의 구현체는 한 클래스가 요청 팩토리와 스트림 팩토리를 겸하므로 그걸 먼저 본다.
     *
     * @param  RequestFactoryInterface|null  $requests  겸하고 있으면 그대로 쓴다
     */
    public static function streamFactory(
        ?StreamFactoryInterface $factory = null,
        ?RequestFactoryInterface $requests = null,
    ): StreamFactoryInterface {
        if ($factory) {
            return $factory;
        }

        if ($requests instanceof StreamFactoryInterface) {
            return $requests;
        }

        if (! class_exists(Psr17FactoryDiscovery::class)) {
            throw new RuntimeException(self::hint('PSR-17 스트림 팩토리'));
        }

        return Psr17FactoryDiscovery::findStreamFactory();
    }

    private static function hint(string $what): string
    {
        return "{$what} 를 찾을 수 없습니다. 직접 넘기거나, 자동 탐색을 쓰려면 "
            .'`composer require php-http/discovery` 와 구현체(예: `guzzlehttp/guzzle`, `nyholm/psr7`)를 설치하세요.';
    }
}
