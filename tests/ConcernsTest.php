<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Tests;

use PHPUnit\Framework\TestCase;
use Sejongtf\Synology\Exceptions\ApiException;
use Sejongtf\Synology\Exceptions\RequestException;
use Sejongtf\Synology\Services\Chat\Api\External;
use Sejongtf\Synology\Services\Chat\Api\Post;
use Sejongtf\Synology\Services\Contacts\Api\Addressbook;
use Sejongtf\Synology\Services\Contacts\Api\Contact;
use Sejongtf\Synology\Tests\Fixtures\FakeConnection;
use Sejongtf\Synology\Tests\Fixtures\FakeResponse;

class ConcernsTest extends TestCase
{
    // --- NormalizesParams ----------------------------------------------------

    public function test_scalars_and_arrays_both_become_json_lists(): void
    {
        $client = new FakeConnection;

        (new Contact($client))->delete(7);
        $this->assertSame('[7]', $client->lastCall()['params']['ids']);

        (new Contact($client))->delete([7, 8]);
        $this->assertSame('[7,8]', $client->lastCall()['params']['ids']);
    }

    public function test_keyed_arrays_still_produce_json_lists(): void
    {
        $client = new FakeConnection;
        (new Addressbook($client))->delete([3 => 7]);

        // 키가 남으면 json_encode 가 {} 를 만들어 DSM 이 거부한다.
        $this->assertSame('[7]', $client->lastCall()['params']['ids']);
    }

    public function test_booleans_become_the_strings_dsm_expects(): void
    {
        $client = new FakeConnection;

        (new Addressbook($client))->create('주소록', is_public: false);
        $this->assertSame('false', $client->lastCall()['params']['is_public']);

        (new Post($client))->delete(1, real_delete: true);
        $this->assertSame('true', $client->lastCall()['params']['real_delete']);
    }

    // --- HandlesBinaryResponse -----------------------------------------------

    public function test_a_binary_body_is_returned_untouched(): void
    {
        $client = new FakeConnection(FakeResponse::binary('ATTACHMENT'));

        $response = (new External($client))->post_file_get('BOT', 1);

        $this->assertSame('ATTACHMENT', (string) $response->toPsrResponse()->getBody());
    }

    public function test_a_json_error_instead_of_a_file_is_raised(): void
    {
        $client = new FakeConnection(FakeResponse::error(['code' => 408]));

        $this->expectException(ApiException::class);

        (new External($client))->post_file_get('BOT', 1);
    }

    /**
     * 파일을 기대한 요청이 JSON 봉투 없이 HTTP 오류만 돌려주는 경우가 있다.
     * Content-Type 만 보면 이걸 파일로 착각한다.
     */
    public function test_a_non_json_failure_status_is_still_an_error(): void
    {
        $client = new FakeConnection(FakeResponse::failedBinary(404));

        $this->expectException(RequestException::class);

        (new External($client))->post_file_get('BOT', 1);
    }
}
