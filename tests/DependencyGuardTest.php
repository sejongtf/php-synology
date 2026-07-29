<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Tests;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * 의존성 규칙 둘을 지킨다.
 *
 * 원래는 Illuminate·Guzzle·Laravel 전역 헬퍼를 걷어낼 때 만든 검사가 함께 있었는데,
 * 그건 한 번 끝난 일이라 지웠다. 여기 남은 둘은 **앞으로도 깨질 수 있는 규칙**이다.
 */
class DependencyGuardTest extends TestCase
{
    /**
     * 런타임에 허용되는 패키지. 전부 **인터페이스 패키지**다.
     *
     * 구현체는 소비자가 고른다 — 이 목록에 `guzzlehttp/guzzle` 같은 이름이 들어오면
     * 그건 규칙이 깨진 것이다. 테스트를 편하게 하려고 구현체를 require 로 옮기는 게
     * 가장 그럴듯한 실수라서 남겨 둔다.
     */
    private const ALLOWED_REQUIRE = [
        'php',
        'psr/http-client',   // PSR-18: Http\Connection 이 소비자의 전송 구현을 받는 지점
        'psr/http-factory',  // PSR-17: 요청/스트림 생성
        'psr/http-message',  // PSR-7
    ];

    public function test_runtime_requires_only_framework_neutral_packages(): void
    {
        $require = array_keys($this->composer()['require'] ?? []);
        $allowed = self::ALLOWED_REQUIRE;

        sort($require);
        sort($allowed);

        $this->assertSame($allowed, $require);
    }

    /**
     * `nyholm/psr7` 은 테스트 전반에서 쓰이므로 `src/` 에서도 손이 갈 수 있다.
     * 그러면 프로덕션 설치에는 그 패키지가 없어 클래스를 못 찾는다.
     */
    public function test_src_does_not_reference_dev_only_packages(): void
    {
        foreach (array_keys($this->composer()['require-dev'] ?? []) as $package) {
            $namespace = match ($package) {
                'nyholm/psr7' => 'Nyholm\\',
                'phpunit/phpunit' => 'PHPUnit\\',
                default => null,
            };

            if ($namespace !== null) {
                $this->assertSame([], $this->filesContaining($namespace), "src/ 가 dev 전용 패키지 [{$package}] 를 참조합니다.");
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function composer(): array
    {
        return json_decode((string) file_get_contents(dirname(__DIR__).'/composer.json'), true);
    }

    /**
     * @return array<int, string>
     */
    private function filesContaining(string $needle): array
    {
        $offenders = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(dirname(__DIR__).'/src', RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php'
                && str_contains((string) file_get_contents($file->getPathname()), $needle)) {
                $offenders[] = $file->getPathname();
            }
        }

        return $offenders;
    }
}
