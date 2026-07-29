<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sejongtf\Synology\Auth\CallableStore;
use Sejongtf\Synology\Auth\InMemoryStore;
use Sejongtf\Synology\Auth\Session;

class SessionTest extends TestCase
{
    public function test_a_session_needs_nothing_but_a_sid(): void
    {
        $session = new Session('SID123');

        $this->assertSame('SID123', $session->sid);
        $this->assertNull($session->synoToken);
        $this->assertFalse($session->hasSynoToken());
    }

    /**
     * 빈 sid 세션은 만들어지는 순간부터 쓸모가 없는데 증상이 하나도 안 나온다.
     *
     * `Api::raw()` 는 빈 sid 를 falsy 로 보고 `_sid` 를 아예 빼고, DSM 은 119 를 주고,
     * `Http\Connection` 의 재인증은 `_sid` 가 실려 있어야 걸리므로 그마저 건너뛴다.
     * 소비자에게는 단서 없는 `null` 만 돌아가고 저장소에는 `''` 가 남아 다음 요청도
     * 똑같다. 그래서 만드는 자리에서 막는다.
     */
    public function test_a_session_refuses_an_empty_sid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Session('');
    }

    public function test_a_whitespace_only_sid_is_empty_too(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Session("  \t ");
    }

    /**
     * 로그인이 `success: true` 를 주면서 `data` 를 안 주는 경우가 여기로 온다.
     * `Authenticator::login()` 이 `Session::fromArray($data ?? [])` 를 부르기 때문이다.
     */
    public function test_an_array_without_a_sid_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Session::fromArray([]);
    }

    public function test_round_trips_through_an_array(): void
    {
        $data = ['sid' => 'SID123', 'synotoken' => 'TOK', 'did' => 'DID'];
        $session = Session::fromArray($data);

        $this->assertSame('SID123', $session->sid);
        $this->assertSame('TOK', $session->synoToken);
        $this->assertSame('DID', $session->did);
        $this->assertTrue($session->hasSynoToken());
        $this->assertSame($data, $session->toArray());
    }

    public function test_array_form_omits_absent_values(): void
    {
        $this->assertSame(['sid' => 'SID123'], (new Session('SID123'))->toArray());
    }

    public function test_in_memory_store_holds_and_forgets(): void
    {
        $store = new InMemoryStore;
        $this->assertNull($store->get());

        $store->put(new Session('SID123'));
        $this->assertSame('SID123', $store->get()->sid);

        $store->forget();
        $this->assertNull($store->get());
    }

    public function test_callable_store_delegates_to_the_consumer(): void
    {
        $external = ['sid' => 'FROM_CACHE'];
        $forgotten = false;

        $store = new CallableStore(
            get: fn () => $external,
            put: function (Session $s) use (&$external) {
                $external = $s->toArray();
            },
            forget: function () use (&$forgotten) {
                $forgotten = true;
            },
        );

        $this->assertSame('FROM_CACHE', $store->get()->sid);

        $store->put(new Session('NEW', 'TOK'));
        $this->assertSame(['sid' => 'NEW', 'synotoken' => 'TOK'], $external);

        $store->forget();
        $this->assertTrue($forgotten);
    }

    /**
     * 캐시에 객체를 직렬화해 넣기 싫은 경우가 흔해서 배열과 sid 문자열도 받는다.
     */
    public function test_callable_store_accepts_a_bare_sid_string(): void
    {
        $store = new CallableStore(get: fn () => 'RAW_SID');

        $this->assertSame('RAW_SID', $store->get()->sid);
    }

    public function test_callable_store_treats_empty_results_as_no_session(): void
    {
        $this->assertNull((new CallableStore(get: fn () => null))->get());
        $this->assertNull((new CallableStore(get: fn () => []))->get());
        $this->assertNull((new CallableStore(get: fn () => ''))->get());
    }

    public function test_callable_store_without_writers_is_read_only(): void
    {
        $store = new CallableStore(get: fn () => 'RAW_SID');

        // put/forget 을 안 넘겨도 터지지 않아야 한다. 읽기 전용 소비자를 위한 경우다.
        $store->put(new Session('X'));
        $store->forget();

        $this->assertSame('RAW_SID', $store->get()->sid);
    }
}
