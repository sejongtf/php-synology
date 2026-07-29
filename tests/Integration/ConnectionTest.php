<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Tests\Integration;

/**
 * 로그인 → 디스커버리 → 읽기 호출이 실제로 되는지.
 */
class ConnectionTest extends IntegrationTestCase
{
    public function test_it_can_reach_the_nas_and_obtain_a_session(): void
    {
        $syno = $this->synology();

        // 지연 로그인이므로 실제 요청은 여기서 처음 나간다.
        $sid = $syno->connection()->getSessionId();

        $this->assertNotEmpty($sid, '세션을 얻지 못했습니다.');
        $this->report('세션 확보 OK (sid 길이 '.strlen((string) $sid).')');
    }

    public function test_discovery_returns_the_api_catalogue(): void
    {
        $registry = $this->synology()->discover();

        $this->assertNotEmpty($registry->all(), 'SYNO.API.Info 가 빈 응답을 줬습니다.');
        $this->report('디스커버리에서 API '.count($registry->all()).'개 확인');

        $this->assertTrue($registry->has('SYNO.API.Auth'));
    }

    public function test_a_read_only_call_succeeds(): void
    {
        $syno = $this->synology();

        $response = $syno->contacts->info->raw('get_timezone');

        $this->assertNotNull($response);
        $this->assertTrue(
            $response->success(),
            'SYNO.Contacts.Info::get_timezone 실패: '.json_encode($response->error()),
        );

        $this->report('SYNO.Contacts.Info::get_timezone OK');
    }
}
