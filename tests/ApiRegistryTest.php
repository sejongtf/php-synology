<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Tests;

use PHPUnit\Framework\TestCase;
use Sejongtf\Synology\Registry\ApiRegistry;
use Sejongtf\Synology\Services\Api\Info;
use Sejongtf\Synology\Tests\Fixtures\FakeConnection;
use Sejongtf\Synology\Tests\Fixtures\FakeResponse;

class ApiRegistryTest extends TestCase
{
    /** 공식 문서의 응답 예시 그대로. */
    private function registry(): ApiRegistry
    {
        return ApiRegistry::fromInfoResponse([
            'SYNO.API.Auth' => ['path' => 'entry.cgi', 'minVersion' => 1, 'maxVersion' => 7],
            'SYNO.FileStation.List' => ['path' => 'entry.cgi', 'requestFormat' => 'JSON', 'minVersion' => 1, 'maxVersion' => 2],
            'SYNO.VideoStation.Info' => ['path' => 'VideoStation/info.cgi', 'minVersion' => 1, 'maxVersion' => 1],
        ]);
    }

    public function test_resolves_per_api_paths(): void
    {
        // entry.cgi 로 다 통합됐다고 가정하면 안 된다. 문서 예시에도 예외가 있다.
        $this->assertSame('entry.cgi', $this->registry()->path('SYNO.API.Auth'));
        $this->assertSame('VideoStation/info.cgi', $this->registry()->path('SYNO.VideoStation.Info'));
    }

    public function test_unknown_apis_fall_back_to_entry_cgi(): void
    {
        $registry = new ApiRegistry;

        $this->assertFalse($registry->has('SYNO.Whatever'));
        $this->assertSame('entry.cgi', $registry->path('SYNO.Whatever'));
        $this->assertNull($registry->maxVersion('SYNO.Whatever'));
    }

    public function test_reports_when_params_must_be_json_encoded(): void
    {
        $this->assertTrue($this->registry()->requiresJsonParams('SYNO.FileStation.List'));
        $this->assertFalse($this->registry()->requiresJsonParams('SYNO.API.Auth'));
        $this->assertFalse($this->registry()->requiresJsonParams('SYNO.Unknown'));
    }

    public function test_clamps_versions_into_the_supported_range(): void
    {
        $registry = $this->registry();

        // 범위를 넘겨 보내면 DSM 이 104 를 돌려준다. 미리 맞춘다.
        $this->assertSame(2, $registry->negotiate('SYNO.FileStation.List', 5));
        $this->assertSame(1, $registry->negotiate('SYNO.FileStation.List', 1));
        $this->assertSame(7, $registry->negotiate('SYNO.API.Auth', null));
    }

    public function test_unknown_apis_keep_the_requested_version(): void
    {
        $this->assertSame(3, (new ApiRegistry)->negotiate('SYNO.Whatever', 3));
        $this->assertNull((new ApiRegistry)->negotiate('SYNO.Whatever', null));
    }

    public function test_merge_lets_later_lookups_win(): void
    {
        $merged = $this->registry()->merge(ApiRegistry::fromInfoResponse([
            'SYNO.API.Auth' => ['path' => 'auth.cgi', 'minVersion' => 1, 'maxVersion' => 3],
        ]));

        $this->assertSame('auth.cgi', $merged->path('SYNO.API.Auth'));
        $this->assertSame(3, $merged->maxVersion('SYNO.API.Auth'));
        $this->assertSame('entry.cgi', $merged->path('SYNO.FileStation.List'));
    }

    public function test_info_query_joins_names_and_skips_authentication(): void
    {
        $client = new FakeConnection(FakeResponse::json(['SYNO.API.Auth' => []]));
        $info = new Info($client);

        $info->query(['SYNO.API.Auth', 'SYNO.FileStation']);

        $call = $client->lastCall();
        $this->assertSame('SYNO.API.Auth,SYNO.FileStation', $call['params']['query']);

        // 디스커버리는 로그인 전에도 되어야 하므로 _sid 를 붙이지 않는다.
        $this->assertArrayNotHasKey('_sid', $call['params']);
    }

    public function test_info_query_without_arguments_asks_for_everything(): void
    {
        $client = new FakeConnection;
        (new Info($client))->query();

        $this->assertArrayNotHasKey('query', $client->lastCall()['params']);
    }
}
