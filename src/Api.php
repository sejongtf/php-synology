<?php

declare(strict_types=1);

namespace Sejongtf\Synology;

use BadMethodCallException;
use Sejongtf\Synology\Contracts\Connection;
use Sejongtf\Synology\Message\Response;

/**
 * DSM 엔드포인트 하나.
 *
 * 하위 클래스는 `API_NAME` 과 `$methods`(메서드 => 버전)만 선언하면 되고, 선언한
 * 메서드는 PHP 메서드가 없어도 `__call` 로 불린다. 파라미터가 많거나 자주 쓰는 것만
 * 실제 시그니처를 채운다.
 */
abstract class Api
{
    const API_NAME = '';

    /**
     * 이 API 가 세션을 쓰는지.
     *
     * `false` 면 `raw()` 가 `_sid` 를 붙이지 않는다. 레지스트리의 `authLevel: 0` 에
     * 해당하며(봇 토큰 인증, 로그인 이전 조회, 로그인 그 자체), 세션이 있어도 붙이면
     * 그 의도가 깨진다. `_sid` 가 빠지면 `Http\Connection` 도 `SynoToken` 을 붙이지
     * 않고 세션 만료 재시도도 걸지 않는다 — 이 상수 하나에 셋이 딸려 있다.
     *
     * `API_NAME` 과 마찬가지로 **레지스트리에서 온 사실**이라 상수다. 예전에는
     * 프로퍼티 + `withAuth()` 였는데, 호출자가 한 번도 없었던 데다 `Service` 가 API
     * 인스턴스를 캐시하므로 한 번 뒤집으면 그 서비스에 계속 남는 함정이었다.
     */
    const AUTH = true;

    /** @var array<string, int> DSM 메서드 이름 => 보낼 버전 */
    protected array $methods = [];

    protected ?Connection $connection = null;

    public function __construct(?Connection $connection = null)
    {
        $this->setConnection($connection);
    }

    public function getApiName(): string
    {
        return static::API_NAME;
    }

    /**
     * @return array<string, int>
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    public function setConnection(?Connection $connection): static
    {
        $this->connection = $connection;

        return $this;
    }

    public function getConnection(): ?Connection
    {
        return $this->connection;
    }

    /**
     * `raw()` 가 `_sid` 를 붙일지 판단하는 유일한 자리.
     *
     * 하위 클래스가 조건부로 정해야 한다면 여기를 오버라이드한다. 기본은 `AUTH` 상수다.
     */
    protected function getAuth(): bool
    {
        return static::AUTH;
    }

    /**
     * 지금 연결의 sid. 연결이 교체되면 그 즉시 새 세션을 따른다.
     */
    protected function getSid(): ?string
    {
        return $this->getConnection()?->getSessionId();
    }

    /**
     * 요청을 보내고 원본 Response 를 반환한다.
     *
     * request() 는 편의상 Response::data() 만 돌려주는데, 이는 성공+빈 데이터(빈 배열/객체)와
     * data 누락을 구분하지 못한다. 그런 구분이나 헤더/원문이 필요한 소비자는 이 메서드로 Response 에 직접 접근한다.
     *
     * @param  array<string, mixed>  $params
     */
    public function raw(string $method, ?int $version = null, array $params = []): ?Response
    {
        if (! ($connection = $this->getConnection())) {
            return null;
        }

        $version ??= $this->methods[$method] ?? 1;

        if ($this->getAuth() && $sid = $this->getSid()) {
            $params['_sid'] = $sid;
        }

        // DSM 은 배열 파라미터를 JSON 문자열로 기대한다. 배열 값만 자동 인코딩하고,
        // 이미 문자열로 넘어온 값은 그대로 통과시킨다(이중 인코딩 없음).
        //
        // **인코딩은 여기 한 곳에서만 한다.** Api 메서드가 손으로 json_encode 해서 넘기면
        // 여기서 다시 하지는 않지만 플래그가 달라져 같은 값이 두 모양으로 나간다.
        // JSON_UNESCAPED_UNICODE 는 한글을 \uXXXX 로 부풀리지 않기 위한 것이고,
        // Http\Connection::prepare() 의 방어용 분기도 같은 플래그를 쓴다.
        $params = array_map(
            fn ($value) => is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value,
            $params,
        );

        return $connection->request($this->getApiName(), $version, $method, $params);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<mixed>|null
     */
    public function request(string $method, ?int $version = null, array $params = []): ?array
    {
        return $this->raw($method, $version, $params)?->data();
    }

    /**
     * @param  array<int, mixed>  $params
     * @return array<mixed>|null
     */
    public function __call(string $method, array $params = []): ?array
    {
        // 공개 메서드는 애초에 __call 로 오지 않는다. 여기 도달했는데 method_exists 가 참이면
        // private/protected 라는 뜻이므로, 호출을 넘겨주면 가시성을 우회시키는 셈이 된다.
        // 선언된 DSM 메서드만 매직 호출로 처리한다.
        if (array_key_exists($method, $this->methods)) {
            return $this->request($method, null, ...$params);
        }

        throw new BadMethodCallException("Unable to call method. Public method [{$method}] not found");
    }
}
