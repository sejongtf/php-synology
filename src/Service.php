<?php

declare(strict_types=1);

namespace Sejongtf\Synology;

use ArrayAccess;
use LogicException;
use Sejongtf\Synology\Contracts\Connection;

/**
 * DSM API 묶음 하나.
 *
 * 하위 클래스는 `$apis` 맵만 선언하고, 인스턴스 해석과 캐시는 여기서 한다.
 * `$chat->channel` 과 `$chat['channel']` 이 같은 인스턴스를 준다.
 *
 * @implements ArrayAccess<string, Api|null>
 */
abstract class Service implements ArrayAccess
{
    /** @var array<string, class-string<Api>|Api|callable> API 정의. 절대 덮어쓰지 않는다. */
    protected array $apis = [];

    /** @var array<string, Api> 인스턴스화된 API 캐시(정의와 분리해 재생성이 가능하도록). */
    protected array $resolved = [];

    public function __construct(protected Connection $connection) {}

    public function getConnection(): Connection
    {
        return $this->connection;
    }

    public function setConnection(Connection $connection): static
    {
        $this->connection = $connection;

        // 캐시된 API 인스턴스가 오래된 연결(구 세션/endpoint)을 계속 쓰지 않게 한다.
        // - setConnection 지원(Api 계열, 현재 모든 API): 새 연결을 주입.
        // - 미지원: 캐시에서 버려 다음 접근 시 새 연결로 재생성. $apis 에 Api 를 상속하지 않는
        //   클래스나 클로저를 등록하는 경우를 위한 방어 코드다.
        foreach ($this->resolved as $name => $api) {
            if (method_exists($api, 'setConnection')) {
                $api->setConnection($this->connection);
            } else {
                unset($this->resolved[$name]);
            }
        }

        return $this;
    }

    public function hasApi(string $name): bool
    {
        return array_key_exists($name, $this->apis);
    }

    public function getApi(string $name): ?Api
    {
        // 인스턴스를 캐시해 매 접근마다 새로 만들지 않는다.
        if (isset($this->resolved[$name])) {
            return $this->resolved[$name];
        }

        $api = $this->apis[$name] ?? null;

        if (! $api) {
            return null;
        }

        if (is_callable($api)) {
            $api = $api();
        }

        if (! is_object($api)) {
            $api = new $api($this->getConnection());
        }

        return $this->resolved[$name] = $api;
    }

    /**
     * 이름으로 꺼낼 수 있는 건 **등록된 API 뿐이다.**
     *
     * 예전에는 `property_exists($this, $key)` 폴백이 있어서 `$chat->connection`,
     * `$chat->apis`, `$chat->resolved` 가 밖에서 그대로 읽혔다. protected 로 선언해 둔
     * 것이 매직 접근자 때문에 공개되는 셈이었다. `Api::__call` 이 정확히 같은 우회를
     * 막고 있으니 여기도 막는다 — 연결이 필요하면 `getConnection()` 이 있다.
     */
    public function getAttribute(string $key): ?Api
    {
        return $this->hasApi($key) ? $this->getApi($key) : null;
    }

    public function __get(string $key): ?Api
    {
        return $this->getAttribute($key);
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->hasApi($offset);
    }

    public function offsetGet(mixed $offset): ?Api
    {
        return $this->getAttribute($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('Service 는 읽기 전용이다. API 는 $apis 에 선언한다.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('Service 는 읽기 전용이다. API 는 $apis 에 선언한다.');
    }
}
