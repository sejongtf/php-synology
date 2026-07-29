<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Tests\Integration;

use Nyholm\Psr7\Response as PsrResponse;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * 통합 테스트용 최소 PSR-18 클라이언트.
 *
 * 이걸 위해 Guzzle 같은 구현체를 require-dev 에 넣지 않는다. curl 로 충분하고,
 * 소비자가 자기 클라이언트를 어떻게 끼워 넣는지 보여주는 예시도 된다.
 *
 * NAS 가 자체 서명 인증서를 쓰는 경우가 많아 TLS 검증을 끌 수 있게 해 뒀다.
 */
final class CurlClient implements ClientInterface
{
    public function __construct(
        private readonly bool $verifyTls = true,
        private readonly int $timeout = 30,
    ) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $headers = [];

        foreach ($request->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $headers[] = "{$name}: {$value}";
            }
        }

        $handle = curl_init();

        curl_setopt_array($handle, [
            CURLOPT_URL => (string) $request->getUri(),
            CURLOPT_CUSTOMREQUEST => $request->getMethod(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => $this->verifyTls,
            CURLOPT_SSL_VERIFYHOST => $this->verifyTls ? 2 : 0,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $body = (string) $request->getBody();

        if ($body !== '') {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($handle);

        if ($raw === false) {
            $error = curl_error($handle);
            curl_close($handle);

            throw new class("curl: {$error}") extends RuntimeException implements ClientExceptionInterface {};
        }

        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($handle, CURLINFO_HEADER_SIZE);
        curl_close($handle);

        return new PsrResponse(
            $status,
            self::parseHeaders(substr((string) $raw, 0, $headerSize)),
            substr((string) $raw, $headerSize),
        );
    }

    /**
     * @return array<string, string>
     */
    private static function parseHeaders(string $block): array
    {
        $headers = [];

        foreach (preg_split('/\r?\n/', trim($block)) ?: [] as $line) {
            if (! str_contains($line, ':')) {
                continue;   // "HTTP/1.1 200 OK" 같은 상태 줄
            }

            [$name, $value] = explode(':', $line, 2);
            $headers[trim($name)] = trim($value);
        }

        return $headers;
    }
}
