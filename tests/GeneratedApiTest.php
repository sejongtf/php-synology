<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sejongtf\Synology\Api;
use Sejongtf\Synology\Service;
use Sejongtf\Synology\Services\Api\Auth as AuthApi;
use Sejongtf\Synology\Services\Api\Info as InfoApi;
use Sejongtf\Synology\Synology;
use Sejongtf\Synology\Tests\Fixtures\FakeConnection;

/**
 * 코드젠 결과물이 실제로 쓸 수 있는 상태인지 확인한다.
 *
 * 클래스를 많이 찍어내는 만큼, 등록이 빠졌거나 이름이 어긋난 것을 사람이 눈으로
 * 잡기는 어렵다.
 */
class GeneratedApiTest extends TestCase
{
    /**
     * @return array<string, class-string<Service>>
     */
    private function services(): array
    {
        return (new ReflectionClass(Synology::class))->getConstant('SERVICES');
    }

    /**
     * `const AUTH` 는 레지스트리의 `authLevel` 과 일치해야 한다.
     *
     * 이 값 하나에 셋이 딸려 있다 — `_sid` 부착, `SynoToken` 부착, 세션 만료 재시도.
     * 그런데 틀려도 증상이 애매하다. `false` 여야 할 곳이 `true` 면 봇 토큰 API 가
     * 쓸데없이 세션을 끌어다 쓰고(자격증명이 있으면 지연 로그인까지 유발한다),
     * 반대면 인증이 필요한 API 가 조용히 401 계열로 실패한다.
     *
     * 클래스별로 못 박는 대신 **덤프와 대조**한다. 덤프 자체가 스냅샷이라 버전 범위는
     * 믿지 않지만(CLAUDE.md), `authLevel` 은 API 의 성격이라 버전에 따라 흔들리지 않는다.
     */
    public function test_the_auth_constant_matches_the_registry(): void
    {
        $authLevels = [];

        foreach (glob(dirname(__DIR__).'/resources/registry/*.lib.json') ?: [] as $file) {
            foreach (json_decode((string) file_get_contents($file), true) ?? [] as $name => $spec) {
                $authLevels[$name] = $spec['authLevel'] ?? null;
            }
        }

        $this->assertNotEmpty($authLevels, '레지스트리를 읽지 못했습니다.');

        $checked = 0;

        foreach ($this->registeredApis() as $api) {
            $level = $authLevels[$api->getApiName()] ?? null;

            if ($level === null) {
                continue;   // 덤프에 없는 API (SYNO.API.* 이전 덤프 등)
            }

            $this->assertSame(
                $level === 0,
                (new ReflectionClass($api))->getConstant('AUTH') === false,
                sprintf(
                    '%s 의 AUTH 가 레지스트리 authLevel(%d) 과 어긋납니다.',
                    $api->getApiName(),
                    $level,
                ),
            );
            $checked++;
        }

        $this->assertGreaterThan(60, $checked, '대조한 API 가 너무 적습니다.');
    }

    /**
     * 등록된 모든 Api 인스턴스. `SYNO.API.*` 는 Service 에 속하지 않아 따로 붙인다.
     *
     * @return array<int, Api>
     */
    private function registeredApis(): array
    {
        $connection = new FakeConnection;
        $apis = [new AuthApi($connection), new InfoApi($connection)];

        foreach ($this->services() as $serviceClass) {
            $service = new $serviceClass($connection);
            $registered = (new ReflectionClass($service))->getProperty('apis')->getValue($service);

            foreach (array_keys($registered) as $key) {
                $apis[] = $service->getApi($key);
            }
        }

        return $apis;
    }

    public function test_every_registered_api_resolves_to_a_usable_api_object(): void
    {
        $client = new FakeConnection;
        $checked = 0;

        foreach ($this->services() as $serviceName => $serviceClass) {
            $service = new $serviceClass($client);
            $apis = (new ReflectionClass($service))->getProperty('apis')->getValue($service);

            $this->assertNotEmpty($apis, "{$serviceClass} 에 등록된 API 가 없습니다.");

            foreach (array_keys($apis) as $key) {
                $api = $service->getApi($key);

                $this->assertInstanceOf(Api::class, $api, "{$serviceName}.{$key} 를 만들 수 없습니다.");
                $this->assertNotSame('', $api->getApiName(), "{$serviceName}.{$key} 에 API_NAME 이 없습니다.");
                $checked++;
            }
        }

        $this->assertGreaterThan(60, $checked, '검사한 API 가 너무 적습니다. 등록이 빠졌을 수 있습니다.');
    }

    public function test_no_two_classes_claim_the_same_dsm_api(): void
    {
        $seen = [];
        $client = new FakeConnection;

        foreach ($this->services() as $serviceClass) {
            $service = new $serviceClass($client);
            $apis = (new ReflectionClass($service))->getProperty('apis')->getValue($service);

            foreach (array_keys($apis) as $key) {
                $name = $service->getApi($key)->getApiName();

                $this->assertArrayNotHasKey($name, $seen, sprintf(
                    '%s 이 두 곳에 등록돼 있습니다: %s / %s::%s',
                    $name,
                    $seen[$name] ?? '?',
                    $serviceClass,
                    $key,
                ));

                $seen[$name] = $serviceClass.'::'.$key;
            }
        }
    }

    /**
     * `$apis` 키는 클래스 이름의 snake_case 여야 한다. 어긋나면 `@property` 독블럭과
     * 실제 접근 이름이 달라져서 IDE 자동완성이 거짓말을 한다.
     */
    public function test_registration_keys_match_their_class_names(): void
    {
        $client = new FakeConnection;

        foreach ($this->services() as $serviceClass) {
            $service = new $serviceClass($client);
            $apis = (new ReflectionClass($service))->getProperty('apis')->getValue($service);

            foreach ($apis as $key => $class) {
                $short = rtrim((new ReflectionClass($class))->getShortName(), '_');
                $expected = strtolower((string) preg_replace(
                    ['/(?<=[a-z0-9])(?=[A-Z])/', '/(?<=[A-Z])(?=[A-Z][a-z])/'],
                    '_',
                    $short,
                ));

                $this->assertSame($expected, $key, "{$class} 의 등록 키가 어긋납니다.");
            }
        }
    }

    public function test_every_declared_method_has_a_positive_version(): void
    {
        $client = new FakeConnection;

        foreach ($this->services() as $serviceClass) {
            $service = new $serviceClass($client);
            $apis = (new ReflectionClass($service))->getProperty('apis')->getValue($service);

            foreach (array_keys($apis) as $key) {
                $api = $service->getApi($key);

                foreach ($api->getMethods() as $method => $version) {
                    $this->assertIsInt($version, "{$api->getApiName()}::{$method} 의 버전이 정수가 아닙니다.");
                    $this->assertGreaterThan(0, $version, "{$api->getApiName()}::{$method} 의 버전이 이상합니다.");
                }
            }
        }
    }

    /**
     * 생성기를 다시 돌려도 아무것도 바뀌지 않아야 한다. 바뀐다면 커밋된 생성물이
     * 레지스트리나 설정과 어긋났다는 뜻이다.
     */
    public function test_the_generator_is_idempotent(): void
    {
        $root = dirname(__DIR__);

        exec(
            escapeshellcmd(PHP_BINARY).' '.escapeshellarg($root.'/tools/generate-apis.php').' --diff 2>&1',
            $output,
            $exit,
        );

        $this->assertSame(
            0,
            $exit,
            "생성기를 다시 돌리면 결과가 달라집니다. `php tools/generate-apis.php` 를 실행하고 커밋하세요.\n"
                .implode("\n", $output),
        );
    }
}
