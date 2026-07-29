<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Tests\Integration;

use Sejongtf\Synology\Http\Connection;
use Sejongtf\Synology\Http\Endpoint;

/**
 * POST 전송이 기대는 DSM 의 규칙 셋을 실기기로 못 박는다.
 *
 * 셋 다 문서에 없고, 단위 테스트로는 확인할 수 없으며, 어겼을 때 나오는 오류 코드가
 * 원인을 전혀 가리키지 않는다(119 "SID not found", 101 "no parameter").
 * DSM 업데이트로 규칙이 달라지면 여기서 먼저 드러나야 한다.
 *
 * 라이브러리를 거치지 않고 배치만 바꿔 보내므로 `Http\Connection` 의 선택이
 * 여전히 옳은지를 바깥에서 검증하는 셈이다. 전부 읽기 전용 호출이다.
 */
class TransportTest extends IntegrationTestCase
{
    /**
     * 이 클래스만 라이브러리를 거치지 않고 직접 POST 하므로 `synology()` 안에 있는
     * 스킵 가드를 타지 않는다. 여기서 한 번 더 막아 준다 — 설정이 없는 곳에서
     * 이 묶음은 **에러가 아니라 skip** 이어야 한다.
     */
    protected function setUp(): void
    {
        if (self::env('SYNOLOGY_URL') === null) {
            $this->markTestSkipped(
                'SYNOLOGY_URL 이 없어 건너뜁니다. phpunit.xml.dist 를 phpunit.xml 로 복사해 값을 채우세요.',
            );
        }
    }

    /**
     * `SynoToken` 을 본문에 실으면 CSRF 검사가 읽지 못해 119 로 떨어진다.
     */
    public function test_the_syno_token_is_not_read_from_the_body(): void
    {
        $connection = $this->synology()->connection();

        if (! $connection instanceof Connection) {
            $this->markTestSkipped('기본 Http\Connection 이 아닙니다.');
        }

        $session = $connection->getSessionStore()->get();

        if (! $session?->hasSynoToken()) {
            $this->markTestSkipped('이 세션에는 SynoToken 이 없어 규칙을 확인할 수 없습니다.');
        }

        $route = ['api' => 'SYNO.Contacts.Info', 'version' => 1, 'method' => 'get_timezone'];

        $correct = $this->post($route + ['_sid' => $session->sid, 'SynoToken' => $session->synoToken], $route);
        $wrong = $this->post($route + ['_sid' => $session->sid], $route + ['SynoToken' => $session->synoToken]);

        $this->assertSame(true, $correct['success'] ?? null, 'SynoToken 을 쿼리로 보냈는데 실패했습니다.');
        $this->assertNotSame(true, $wrong['success'] ?? null, 'SynoToken 을 본문에 실었는데 통과했습니다 — 규칙이 바뀌었을 수 있습니다.');

        $this->report('SynoToken 본문 → '.json_encode($wrong['error'] ?? null).', 쿼리 → 정상');
    }

    /**
     * 본문이 빈 POST 는 라우팅이 쿼리에 멀쩡히 있어도 101 로 거절된다.
     *
     * 그래서 `Http\Connection` 는 `SynoToken` 을 뺀 요청 전체를 본문에 싣는다 —
     * `api`/`version`/`method` 가 늘 있으므로 본문이 빌 일이 없다. 파라미터만 본문에
     * 담는 배치로 바꾸면 `get_timezone` 처럼 인자 없는 호출이 전부 여기 걸린다.
     */
    public function test_an_empty_body_is_rejected_even_with_routing_in_the_url(): void
    {
        $route = ['api' => 'SYNO.API.Info', 'version' => 1, 'method' => 'query'];

        $empty = $this->post($route, []);
        $filled = $this->post($route, $route);

        $this->assertNotSame(true, $empty['success'] ?? null, '빈 본문이 통과했습니다 — 라우팅 중복이 이제 불필요할 수 있습니다.');
        $this->assertSame(true, $filled['success'] ?? null, '본문에 라우팅을 실었는데도 실패했습니다.');

        $this->report('빈 본문 → '.json_encode($empty['error'] ?? null).', 라우팅 실은 본문 → 정상');
    }

    /**
     * 쿼리와 본문에 같은 키가 다른 값으로 있으면 본문이 이긴다.
     *
     * 지금 배치는 키를 겹쳐 보내지 않으므로 여기에 기대지 않는다. 다만 배치를 다시
     * 손볼 때 "쿼리에도 넣어 두면 되겠지" 가 통하지 않는다는 걸 남겨 둔다.
     */
    public function test_the_body_wins_when_a_key_appears_in_both(): void
    {
        $route = ['api' => 'SYNO.API.Info', 'version' => 1, 'method' => 'query'];

        $result = $this->post($route, ['version' => 99] + $route);

        $this->assertNotSame(true, $result['success'] ?? null, '본문의 version=99 가 무시됐습니다 — 우선순위가 바뀌었습니다.');
        $this->report('쿼리 version=1 / 본문 version=99 → '.json_encode($result['error'] ?? null));
    }

    /**
     * 배치를 마음대로 정해 POST 한다.
     *
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function post(array $query, array $body): array
    {
        $verify = self::env('SYNOLOGY_VERIFY_TLS', '1') === '1';

        $handle = curl_init();

        curl_setopt_array($handle, [
            CURLOPT_URL => (new Endpoint((string) self::env('SYNOLOGY_URL')))->url('entry.cgi')
                .'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986),
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($body, '', '&', PHP_QUERY_RFC3986),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => $verify,
            CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
            CURLOPT_TIMEOUT => 30,
        ]);

        $raw = curl_exec($handle);
        curl_close($handle);

        return json_decode((string) $raw, true) ?: [];
    }
}
