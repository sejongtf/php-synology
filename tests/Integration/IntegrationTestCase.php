<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Tests\Integration;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Sejongtf\Synology\Synology;

/**
 * 실제 NAS 를 상대로 도는 테스트의 바탕.
 *
 * **환경변수가 없으면 전부 건너뛴다.** 그래서 자격증명이 없는 곳에서도
 * `vendor/bin/phpunit` 는 그대로 초록색이다.
 *
 * 켜는 방법은 `phpunit.xml.dist` 를 `phpunit.xml` 로 복사해 값을 채우는 것이다
 * (`phpunit.xml` 은 gitignore 돼 있다).
 *
 *     vendor/bin/phpunit --testsuite integration
 *
 * 여기 있는 테스트는 **읽기 전용**이다. NAS 의 데이터를 바꾸지 않는다.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected function synology(): Synology
    {
        $url = self::env('SYNOLOGY_URL');

        if ($url === null) {
            $this->markTestSkipped(
                'SYNOLOGY_URL 이 없어 건너뜁니다. phpunit.xml.dist 를 phpunit.xml 로 복사해 값을 채우세요.',
            );
        }

        $http = new CurlClient(self::env('SYNOLOGY_VERIFY_TLS', '1') === '1');
        $factory = new Psr17Factory;

        // sid 를 직접 줬으면 로그인하지 않는다. 자격증명 없이도 돌 수 있어야 한다.
        if ($sid = self::env('SYNOLOGY_SID')) {
            return Synology::withSession($url, $sid, $http, $factory);
        }

        $account = self::env('SYNOLOGY_ACCOUNT');
        $password = self::env('SYNOLOGY_PASSWORD');

        if ($account === null || $password === null) {
            $this->markTestSkipped('SYNOLOGY_SID 또는 SYNOLOGY_ACCOUNT/SYNOLOGY_PASSWORD 가 필요합니다.');
        }

        return Synology::connect(
            $url,
            $account,
            $password,
            session: self::env('SYNOLOGY_SESSION'),
            otpCode: self::env('SYNOLOGY_OTP'),
            http: $http,
            requests: $factory,
        );
    }

    /**
     * 비어 있는 값은 없는 것으로 본다. phpunit.xml 에 빈 문자열이 남아 있어도
     * 설정되지 않은 것으로 다뤄야 하기 때문이다.
     */
    protected static function env(string $name, ?string $default = null): ?string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);

        if ($value === false || $value === null || trim((string) $value) === '') {
            return $default;
        }

        return trim((string) $value);
    }

    /**
     * 사람이 읽을 결과를 표준 오류로 낸다.
     *
     * 이 묶음의 목적은 통과/실패보다 **NAS 가 실제로 어떻게 응답하는지 알아내는 것**이라
     * 확인된 내용을 눈에 보이게 남긴다.
     */
    protected function report(string $line): void
    {
        fwrite(STDERR, "\n    ▸ {$line}");
    }
}
