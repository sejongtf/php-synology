<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Tests\Integration;

/**
 * 미해결 질문 하나를 실기기로 확인한다.
 *
 * 공식 문서의 예시는 **문자열 파라미터도 따옴표로 감싸서** 보낸다.
 *
 *     ...&method=status&taskid=%2251CBD95028B22AED%22     ← taskid="51CB..."
 *     ...&path=[...]&mode=%22open%22                      ← mode="open"
 *
 * `SYNO.API.Info` 가 알려 주는 `requestFormat: "JSON"` 이 그 뜻이다 —
 * 그 API 의 파라미터 값을 전부 JSON 인코딩하라는 것.
 *
 * 그런데 이 패키지는 지금 **배열만** 인코딩하고 문자열은 그대로 보낸다
 * (`Api::raw()` 와 `Http\Connection::prepare()`). 지금까지 동작해 왔으므로 바꾸지 않았지만,
 * DSM 이 둘 다 받아 주는 것인지 아니면 어느 한쪽만 되는 것인지 확인이 필요하다.
 *
 * 이 테스트는 답을 단정하지 않고 둘 다 보내 보고 결과를 보고한다.
 */
class ParamEncodingTest extends IntegrationTestCase
{
    /**
     * 문자열 파라미터를 날것으로 보낼 때와 JSON 으로 감싸 보낼 때를 비교한다.
     *
     * **성공 여부만 봐서는 안 된다.** `SYNO.API.Info::query` 는 필터가 파싱되지 않아도
     * `success: true` 를 돌려준다 — 걸러지지 않은 전체 목록이 오거나 빈 객체가 온다.
     * 둘 다 `success() && hasData()` 를 만족하므로, 그것만 보면 "양쪽 다 된다" 는
     * 잘못된 결론이 나온다.
     *
     * 그래서 **필터가 실제로 걸렸는지**로 판정한다:
     * 요청한 이름이 결과에 있고, 동시에 전체 목록보다 적게 와야 한다.
     */
    public function test_compares_raw_and_json_encoded_string_parameters(): void
    {
        $client = $this->synology()->connection();

        $everything = $client->request('SYNO.API.Info', 1, 'query');
        $this->assertTrue($everything->success(), '필터 없는 조회부터 실패했습니다.');

        $total = count($everything->data() ?? []);
        $this->assertGreaterThan(1, $total, '전체 목록이 비어 비교 기준을 세울 수 없습니다.');

        $raw = $this->filtered($client, 'SYNO.API.Auth', $total);
        $quoted = $this->filtered($client, '"SYNO.API.Auth"', $total);

        $this->report(sprintf('전체 API %d개 기준', $total));
        $this->report('날것 문자열     : '.$raw['verdict']);
        $this->report('JSON 인용 문자열: '.$quoted['verdict']);

        // 지금 패키지가 쓰는 방식(날것)이 실제로 필터링까지 되는지가 핵심이다.
        $this->assertTrue(
            $raw['filtered'],
            "날것 문자열로 필터가 걸리지 않았습니다. 인코딩 방식을 바꿔야 합니다.\n"
                .'  결과: '.$raw['verdict'],
        );

        if ($raw['filtered'] && $quoted['filtered']) {
            $this->report('→ DSM 이 두 형태를 모두 받아들인다. 현행 유지해도 된다.');
        } elseif ($raw['filtered']) {
            $this->report('→ 날것만 통한다. JSON 전면 인코딩으로 바꾸면 안 된다.');
        }
    }

    /**
     * 쉼표로 구분한 목록이 실제로 걸러지는지.
     */
    public function test_a_comma_separated_list_actually_filters(): void
    {
        $client = $this->synology()->connection();

        $total = count($client->request('SYNO.API.Info', 1, 'query')->data() ?? []);
        $result = $client->request('SYNO.API.Info', 1, 'query', [
            'query' => 'SYNO.API.Auth,SYNO.API.Info',
        ]);

        $this->assertTrue($result->success());

        $keys = array_keys($result->data() ?? []);

        $this->assertContains('SYNO.API.Auth', $keys);
        $this->assertContains('SYNO.API.Info', $keys);

        // 전체가 그대로 왔다면 필터가 무시된 것이지 성공한 게 아니다.
        $this->assertLessThan(
            $total,
            count($keys),
            '전체 목록이 그대로 왔습니다. 필터가 무시된 것이지 동작한 게 아닙니다.',
        );

        $this->report(sprintf('쉼표 목록 필터 OK (%d개 요청 → %d개 반환, 전체 %d개)', 2, count($keys), $total));
    }

    /**
     * 한 이름으로 걸러 보고, 필터가 실제로 걸렸는지 판정한다.
     *
     * @return array{filtered: bool, verdict: string}
     */
    private function filtered(object $client, string $query, int $total): array
    {
        $response = $client->request('SYNO.API.Info', 1, 'query', ['query' => $query]);

        if (! $response->success()) {
            return [
                'filtered' => false,
                'verdict' => '요청 실패 '.json_encode($response->error()),
            ];
        }

        $keys = array_keys($response->data() ?? []);
        $count = count($keys);
        $hasTarget = in_array('SYNO.API.Auth', $keys, true);

        return match (true) {
            $count === 0 => ['filtered' => false, 'verdict' => 'success 지만 데이터가 비었다 (필터가 이름을 못 읽음)'],
            $count >= $total => ['filtered' => false, 'verdict' => "success 지만 전체 {$total}개가 그대로 왔다 (필터 무시됨)"],
            ! $hasTarget => ['filtered' => false, 'verdict' => "{$count}개가 왔지만 요청한 이름이 없다"],
            default => ['filtered' => true, 'verdict' => "필터 동작 ({$count}개 반환)"],
        };
    }
}
