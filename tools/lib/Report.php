<?php

declare(strict_types=1);

/**
 * 생성 결과 요약.
 *
 * 특히 중요한 건 drift 다 — 이미 있는 파일과 레지스트리가 어긋나는 지점을 알려 주되
 * 고치지는 않는다. 어긋남이 곧 잘못은 아니기 때문이다
 * (예: `SYNO.Chat.Channel::enter` 는 레지스트리 v5 지만 코드는 v2 로 고정돼 있다).
 */
final class Report
{
    /** @var array<int, array{file: string, api: string}> */
    private array $created = [];

    /** @var array<int, array{api: string, class: string, missing: array, extra: array, pinned: array}> */
    private array $drift = [];

    /** @var array<int, array{file: string, count: int}> */
    private array $services = [];

    /** @var array<int, string> */
    private array $notes = [];

    /** @var array<int, string> */
    private array $problems = [];

    public function created(string $file, string $api): void
    {
        $this->created[] = compact('file', 'api');
    }

    public function drift(string $api, string $class, array $missing, array $extra, array $pinned): void
    {
        $this->drift[] = compact('api', 'class', 'missing', 'extra', 'pinned');
    }

    public function serviceUpdated(string $file, int $count): void
    {
        $this->services[] = compact('file', 'count');
    }

    public function note(string $message): void
    {
        $this->notes[] = $message;
    }

    public function problem(string $message): void
    {
        $this->problems[] = $message;
    }

    public function print(bool $diffOnly): void
    {
        $verb = $diffOnly ? '생성 예정' : '생성함';

        echo "\n";

        if ($this->created) {
            echo count($this->created)."개 Api 클래스 {$verb}\n";
            foreach ($this->created as $item) {
                echo "  + {$item['file']}\n";
            }
            echo "\n";
        }

        if ($this->services) {
            $verb = $diffOnly ? '갱신 예정' : '갱신함';
            echo count($this->services)." 개 Service {$verb}\n";
            foreach ($this->services as $item) {
                echo "  ~ {$item['file']} (API {$item['count']}개)\n";
            }
            echo "\n";
        }

        if ($this->drift) {
            echo count($this->drift)." 개 기존 클래스가 레지스트리와 다릅니다 (건드리지 않음)\n";
            foreach ($this->drift as $item) {
                echo "  · {$item['class']}  ({$item['api']})\n";

                if ($item['pinned']) {
                    foreach ($item['pinned'] as $name => $change) {
                        echo "      버전 핀   {$name}: {$change}\n";
                    }
                }
                if ($item['missing']) {
                    echo '      미선언    '.implode(', ', array_keys($item['missing']))."\n";
                }
                if ($item['extra']) {
                    echo '      레지스트리에 없음  '.implode(', ', array_keys($item['extra']))."\n";
                }
            }
            echo "\n";
        }

        foreach ($this->notes as $note) {
            echo "  note: {$note}\n";
        }

        foreach ($this->problems as $problem) {
            echo "  ⚠ {$problem}\n";
        }

        if (! $this->created && ! $this->services && ! $this->problems) {
            echo "생성할 것이 없습니다. 레지스트리와 코드가 일치합니다.\n";
        }

        echo "\n";
    }

    /**
     * `--diff` 에서 반영할 변경이 남아 있으면 1. CI 에서 멱등성 검사에 쓴다.
     */
    public function exitCode(bool $diffOnly): int
    {
        if ($this->problems) {
            return 2;
        }

        if ($diffOnly && ($this->created || $this->services)) {
            return 1;
        }

        return 0;
    }
}
