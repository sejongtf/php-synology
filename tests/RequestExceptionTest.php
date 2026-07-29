<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Tests;

use Nyholm\Psr7\Response as PsrResponse;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\TestCase;
use Sejongtf\Synology\Exceptions\ApiException;
use Sejongtf\Synology\Exceptions\RequestException;

/**
 * GuzzleHttp\Psr7\Message::bodySummary() 를 자체 구현으로 대체했으므로 동작을 고정해 둔다.
 */
class RequestExceptionTest extends TestCase
{
    public function test_summarises_a_text_body(): void
    {
        $exception = RequestException::make(new PsrResponse(500, [], 'boom'));

        $this->assertSame(500, $exception->getCode());
        $this->assertSame("HTTP request returned status code 500:\nboom\n", $exception->getMessage());
    }

    public function test_omits_summary_for_an_empty_body(): void
    {
        $exception = RequestException::make(new PsrResponse(404, [], ''));

        $this->assertSame('HTTP request returned status code 404', $exception->getMessage());
    }

    public function test_omits_summary_for_a_binary_body(): void
    {
        $exception = RequestException::make(new PsrResponse(200, [], "\x00\x01\x02binary"));

        $this->assertSame('HTTP request returned status code 200', $exception->getMessage());
    }

    public function test_truncates_a_long_body(): void
    {
        $exception = RequestException::make(new PsrResponse(500, [], str_repeat('a', 200)));

        $this->assertStringContainsString(str_repeat('a', 120).' (truncated...)', $exception->getMessage());
        $this->assertStringNotContainsString(str_repeat('a', 121), $exception->getMessage());
    }

    public function test_does_not_split_multibyte_characters_when_truncating(): void
    {
        // 'a' 한 바이트를 앞에 붙여 120 바이트 경계가 한글(3바이트) 중간에 걸리게 만든다.
        $body = 'a'.str_repeat('가', 60);
        $this->assertNotSame(1, preg_match('//u', substr($body, 0, 120)), '경계가 문자 중간에 걸려야 하는 전제');

        $exception = RequestException::make(new PsrResponse(500, [], $body));
        $summary = $exception->getMessage();

        $this->assertStringContainsString('(truncated...)', $summary);
        $this->assertStringContainsString('a'.str_repeat('가', 39), $summary);
        $this->assertTrue(mb_check_encoding($summary, 'UTF-8'), '잘린 요약이 깨진 UTF-8 을 포함하면 안 된다.');
    }

    public function test_omits_summary_for_a_long_binary_body(): void
    {
        // 잘림 경계가 아니라 본문 자체가 UTF-8 이 아닌 경우. 불완전한 문자를 걷어내다가
        // 빈 문자열 + ' (truncated...)' 만 남기면 안 된다.
        $exception = RequestException::make(new PsrResponse(500, [], "\xFF".str_repeat("\x01", 199)));

        $this->assertSame('HTTP request returned status code 500', $exception->getMessage());
        $this->assertStringNotContainsString('truncated', $exception->getMessage());
    }

    public function test_omits_summary_for_a_long_body_with_control_characters(): void
    {
        // NUL 은 UTF-8 로는 유효하지만 인쇄 가능한 문자가 아니다. 잘림 여부와 무관하게 걸러져야 한다.
        $body = str_repeat('a', 50)."\x00".str_repeat('a', 200);

        $this->assertSame(1, preg_match('//u', substr($body, 0, 120)), 'UTF-8 로는 유효해야 하는 전제');
        $this->assertSame('HTTP request returned status code 500', RequestException::make(new PsrResponse(500, [], $body))->getMessage());
    }

    public function test_rewinds_the_body_so_callers_can_read_it_again(): void
    {
        $response = new PsrResponse(500, [], 'boom');

        RequestException::make($response);

        $this->assertSame('boom', (string) $response->getBody());
    }

    public function test_omits_summary_for_an_unreadable_body(): void
    {
        $stream = Stream::create(fopen('php://output', 'w'));

        $exception = RequestException::make(new PsrResponse(500, [], $stream));

        $this->assertSame('HTTP request returned status code 500', $exception->getMessage());
    }

    public function test_keeps_the_psr_response_and_api_exception_hierarchy(): void
    {
        $response = new PsrResponse(200, [], '');
        $exception = new ApiException($response, 105);

        $this->assertInstanceOf(RequestException::class, $exception);
        $this->assertSame($response, $exception->response);
        $this->assertSame(105, $exception->getErrorCode());
        $this->assertSame('Error Code 105: The logged in session does not have permission', $exception->getMessage());
    }
}
