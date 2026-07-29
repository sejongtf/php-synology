<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Tests;

use Nyholm\Psr7\Response as PsrResponse;
use PHPUnit\Framework\TestCase;
use Sejongtf\Synology\Exceptions\ApiException;
use Sejongtf\Synology\Exceptions\RequestException;
use Sejongtf\Synology\Message\Response;

class MessageTest extends TestCase
{
    private function json(string $body, int $status = 200): Response
    {
        return new Response(new PsrResponse($status, ['Content-Type' => 'application/json'], $body));
    }

    // --- Response: success -------------------------------------------------

    public function test_reads_a_success_envelope(): void
    {
        $response = $this->json('{"success":true,"data":{"total":2,"files":[{"name":"a"}]}}');

        $this->assertTrue($response->success());
        $this->assertTrue($response->hasData());
        $this->assertSame(
            ['total' => 2, 'files' => [['name' => 'a']]],
            $response->data(),
        );
    }

    public function test_distinguishes_missing_data_from_empty_data(): void
    {
        $this->assertFalse($this->json('{"success":true}')->hasData());
        $this->assertTrue($this->json('{"success":true,"data":{}}')->hasData());
        $this->assertNull($this->json('{"success":true}')->data());
    }

    public function test_distinguishes_a_json_object_from_a_json_array(): void
    {
        // 연관배열로 디코드하면 {} 와 [] 가 똑같이 [] 가 되어 구분할 수 없다.
        $object = $this->json('{"success":true,"data":{}}');
        $array = $this->json('{"success":true,"data":[]}');

        $this->assertTrue($object->dataIsObject());
        $this->assertFalse($array->dataIsObject());

        $this->assertSame([], $object->dataRaw());
        $this->assertSame([], $array->dataRaw());
    }

    public function test_data_raw_preserves_scalars(): void
    {
        $this->assertSame(7, $this->json('{"success":true,"data":7}')->dataRaw());
        $this->assertNull($this->json('{"success":true}')->dataRaw());
    }

    // --- Response: failure -------------------------------------------------

    public function test_reads_an_error_envelope_with_nested_errors(): void
    {
        $response = $this->json('{"success":false,"error":{"code":1100,"errors":[{"code":408,"path":"/test/:"}]}}');

        $this->assertFalse($response->success());

        $error = $response->error();
        $this->assertSame(1100, $error['code']);

        // 바깥 code 와 errors[].code 는 서로 다른 테이블이다.
        $this->assertSame([['code' => 408, 'path' => '/test/:']], $error['errors']);
    }

    public function test_error_without_details_has_no_errors_key(): void
    {
        $error = $this->json('{"success":false,"error":{"code":101}}')->error();

        $this->assertSame(101, $error['code']);
        $this->assertArrayNotHasKey('errors', $error);
    }

    public function test_throw_raises_an_api_exception_carrying_the_code(): void
    {
        try {
            $this->json('{"success":false,"error":{"code":119}}')->throw();
            $this->fail('ApiException 이 발생해야 한다.');
        } catch (ApiException $e) {
            $this->assertSame(119, $e->getErrorCode());
            $this->assertStringContainsString('Invalid session', $e->getMessage());
        }
    }

    public function test_throw_returns_self_on_success(): void
    {
        $response = $this->json('{"success":true,"data":{}}');

        $this->assertSame($response, $response->throw());
    }

    // --- Response: non-JSON ------------------------------------------------

    public function test_a_binary_body_counts_as_success_and_is_never_decoded(): void
    {
        $response = new Response(new PsrResponse(200, ['Content-Type' => 'application/octet-stream'], 'BINARY'));

        $this->assertFalse($response->isJsonOrPlaintext());
        $this->assertTrue($response->success());
        $this->assertFalse($response->hasData());
        $this->assertNull($response->data());
    }

    public function test_a_non_json_error_status_fails_and_throws_a_request_exception(): void
    {
        // mode=open 다운로드가 실패하면 JSON 봉투가 아니라 HTTP 404 가 온다.
        $response = new Response(new PsrResponse(404, ['Content-Type' => 'text/html'], ''));

        $this->assertFalse($response->success());
        $this->assertNull($response->error());

        $this->expectException(RequestException::class);
        $response->throw();
    }

    public function test_a_malformed_json_body_is_not_a_success_even_with_status_200(): void
    {
        // application/json 이라고 해놓고 봉투로 안 읽히면 상태 코드를 믿을 수 없다.
        // 여기서 성공으로 읽으면 바이너리 엔드포인트가 오류 응답을 파일로 착각한다.
        $response = $this->json('<html>error</html>');

        $this->assertTrue($response->isJsonOrPlaintext());
        $this->assertFalse($response->success());
        $this->assertFalse($response->hasData());

        $this->expectException(RequestException::class);
        $response->throw();
    }

    public function test_a_json_body_that_is_not_an_envelope_is_not_a_success(): void
    {
        // 문법은 맞지만 최상위가 객체가 아니라 봉투가 아니다.
        $this->assertFalse($this->json('[1,2,3]')->success());
        $this->assertFalse($this->json('"just a string"')->success());
    }

    public function test_plaintext_that_is_not_json_still_follows_the_status_code(): void
    {
        // DSM 이 텍스트 파일 다운로드에 text/plain 을 쓴다. 파싱 실패가 곧 오류는 아니다.
        $ok = new Response(new PsrResponse(200, ['Content-Type' => 'text/plain'], "line one\nline two"));
        $bad = new Response(new PsrResponse(500, ['Content-Type' => 'text/plain'], 'boom'));

        $this->assertTrue($ok->success());
        $this->assertFalse($bad->success());
    }

    public function test_plaintext_carrying_a_json_envelope_is_still_parsed(): void
    {
        // DSM 은 오류 봉투를 text/plain 으로 내려주기도 한다.
        $response = new Response(
            new PsrResponse(200, ['Content-Type' => 'text/plain'], '{"success":false,"error":{"code":119}}')
        );

        $this->assertFalse($response->success());
        $this->assertSame(119, $response->errorCode());
    }

    public function test_exposes_headers_and_the_underlying_psr_response(): void
    {
        $psr = new PsrResponse(200, ['Content-Type' => 'application/json', 'X-Trace' => 'abc'], '{"success":true}');
        $response = new Response($psr);

        $this->assertSame('application/json', $response->contentType());
        $this->assertSame('abc', $response->header('X-Trace'));
        $this->assertSame($psr, $response->toPsrResponse());
    }

    // --- 배열로 돌려주는 것의 함의 ------------------------------------------

    /**
     * PHP 배열은 값 타입이라 호출자가 뜯어고쳐도 응답 안쪽에 닿지 않는다.
     * `ResponseData`/`ResponseError` 가 `offsetSet` 을 막아 지키던 성질을
     * 배열이 이미 갖고 있다는 뜻이다.
     */
    public function test_mutating_the_returned_array_cannot_reach_back_into_the_response(): void
    {
        $response = $this->json('{"success":true,"data":{"total":2}}');

        $data = $response->data();
        $data['total'] = 999;
        $data['injected'] = true;

        $this->assertSame(['total' => 2], $response->data());
    }

    public function test_the_error_array_omits_errors_when_there_are_none(): void
    {
        // 상세 오류는 있을 때만 키가 생긴다. 없는 것과 빈 것을 구분해야 한다.
        $this->assertSame(
            ['code' => 408],
            $this->json('{"success":false,"error":{"code":408}}')->error(),
        );

        $this->assertSame(
            ['code' => 1100, 'errors' => [['code' => 408, 'path' => '/test/:']]],
            $this->json('{"success":false,"error":{"code":1100,"errors":[{"code":408,"path":"/test/:"}]}}')->error(),
        );
    }

    public function test_error_code_is_null_when_there_is_no_error_envelope(): void
    {
        $this->assertNull($this->json('{"success":true,"data":{}}')->errorCode());
        $this->assertNull($this->json('not json at all')->errorCode());
        $this->assertSame(119, $this->json('{"success":false,"error":{"code":119}}')->errorCode());
    }
}
