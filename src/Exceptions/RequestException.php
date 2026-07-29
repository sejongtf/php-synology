<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Exceptions;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

class RequestException extends RuntimeException
{
    public function __construct(public ResponseInterface $response, string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Prepare the exception message.
     *
     * @return string
     */
    protected static function prepareMessage(ResponseInterface $response)
    {
        $message = "HTTP request returned status code {$response->getStatusCode()}";

        $summary = static::bodySummary($response);

        return is_null($summary) ? $message : $message .= ":\n{$summary}\n";
    }

    /**
     * 응답 본문의 앞부분을 예외 메시지에 붙일 수 있는 형태로 요약한다.
     *
     * Guzzle 의 Psr7\Message::bodySummary() 를 대체한다. 이 패키지는 PSR-7 인터페이스에만
     * 의존하므로 특정 HTTP 구현체를 쓰지 않는다.
     *
     * @return string|null 읽을 수 없거나 비어 있거나 바이너리로 보이면 null
     */
    protected static function bodySummary(ResponseInterface $response, int $truncateAt = 120)
    {
        $body = $response->getBody();

        if (! $body->isSeekable() || ! $body->isReadable()) {
            return null;
        }

        $size = $body->getSize();

        if ($size === 0) {
            return null;
        }

        $body->rewind();
        $summary = $body->read($truncateAt);
        $body->rewind();

        if ($size > $truncateAt) {
            if (preg_match('//u', $summary) !== 1) {
                $repaired = static::trimTrailingIncompleteUtf8Character($summary);

                // 자르는 지점의 불완전한 문자만으로 설명되지 않는 깨짐이면 본문이 애초에
                // UTF-8 이 아니라는 뜻이다. 억지로 고치면 아래 바이너리 판정이 무력화되므로
                // 여기서 요약을 포기한다.
                if ($repaired === null) {
                    return null;
                }

                $summary = $repaired;
            }

            $summary .= ' (truncated...)';
        }

        // 인쇄 가능한 문자(문자/기호/구두점/공백)로만 이루어졌을 때에만 노출한다.
        if ($summary === '' || preg_match('/[^\pL\pM\pN\pP\pS\pZ\n\r\t]/u', $summary) !== 0) {
            return null;
        }

        return $summary;
    }

    /**
     * 잘린 지점에 걸친 불완전한 멀티바이트 문자만 걷어낸다.
     *
     * UTF-8 문자는 최대 4바이트이므로 최대 3바이트까지만 되돌려 본다. 그 안에서 복구되지
     * 않으면 잘림 경계가 원인이 아니므로 null 을 돌려준다.
     *
     * @return string|null
     */
    private static function trimTrailingIncompleteUtf8Character(string $summary)
    {
        for ($i = 1; $i <= 3 && $i < strlen($summary); $i++) {
            $candidate = substr($summary, 0, -$i);

            if (preg_match('//u', $candidate) === 1) {
                return $candidate;
            }
        }

        return null;
    }

    public static function make(ResponseInterface $response)
    {
        return new static($response, self::prepareMessage($response), $response->getStatusCode());
    }
}
