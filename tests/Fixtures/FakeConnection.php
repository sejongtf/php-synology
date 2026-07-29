<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Tests\Fixtures;

use Sejongtf\Synology\Contracts\Connection;
use Sejongtf\Synology\Message\Response;

/**
 * 요청 인자를 기록하고 미리 지정한 응답을 돌려주는 Contracts\Connection 구현.
 */
class FakeConnection implements Connection
{
    /** @var array<int, array{api: string, version: int, method: string, params: array}> */
    public array $calls = [];

    private ?\Closure $onRequest = null;

    public function __construct(
        private ?Response $response = null,
        private ?string $sessionId = 'SID123',
        private string $endpoint = 'https://nas.example.com/webapi/entry.cgi',
    ) {
        $this->response ??= FakeResponse::json(['ok' => true]);
    }

    public function respondWith(Response $response): self
    {
        $this->response = $response;

        return $this;
    }

    /**
     * 요청이 나갈 때마다 부를 콜백. 폴링처럼 응답이 도중에 바뀌는 상황을 만들 때 쓴다.
     */
    public function onRequest(?\Closure $callback): self
    {
        $this->onRequest = $callback;

        return $this;
    }

    public function request(string $api, int $version, string $method, array $params = []): Response
    {
        $this->calls[] = compact('api', 'version', 'method', 'params');

        if ($this->onRequest) {
            ($this->onRequest)($this->lastCall());
        }

        return $this->response;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    /**
     * @return array{api: string, version: int, method: string, params: array}
     */
    public function lastCall(): array
    {
        return $this->calls[array_key_last($this->calls)];
    }
}
