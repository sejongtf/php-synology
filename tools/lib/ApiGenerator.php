<?php

declare(strict_types=1);

/**
 * 레지스트리에서 Api 클래스와 Service 의 `$apis` 맵을 만든다.
 *
 * 규칙 하나만 기억하면 된다: **이미 있는 Api 파일은 건드리지 않는다.**
 * 손으로 채운 파라미터 시그니처와 의도적인 버전 핀을 지키기 위해서다.
 * 대신 레지스트리와 어긋나는 부분은 리포트로 알려 준다.
 */
final class ApiGenerator
{
    /**
     * 클래스 이름으로 쓸 수 없는 낱말. 뒤에 `_` 를 붙인다(기존 `List_` 규칙).
     */
    private const RESERVED = [
        'abstract', 'and', 'array', 'as', 'break', 'callable', 'case', 'catch', 'class',
        'clone', 'const', 'continue', 'declare', 'default', 'do', 'echo', 'else', 'elseif',
        'empty', 'enddeclare', 'endfor', 'endforeach', 'endif', 'endswitch', 'endwhile',
        'enum', 'eval', 'exit', 'extends', 'final', 'finally', 'fn', 'for', 'foreach',
        'function', 'global', 'goto', 'if', 'implements', 'include', 'instanceof',
        'insteadof', 'interface', 'isset', 'list', 'match', 'namespace', 'new', 'or',
        'print', 'private', 'protected', 'public', 'readonly', 'require', 'return',
        'static', 'switch', 'throw', 'trait', 'try', 'unset', 'use', 'var', 'while',
        'xor', 'yield', 'self', 'parent', 'int', 'float', 'bool', 'string', 'true',
        'false', 'null', 'void', 'iterable', 'object', 'mixed', 'never',
    ];

    public function __construct(
        private readonly string $root,
        private readonly array $config,
    ) {}

    public function run(bool $diffOnly): Report
    {
        $report = new Report;

        foreach ($this->config as $label => $service) {
            $this->generateService($label, $service, $diffOnly, $report);
        }

        $report->print($diffOnly);

        return $report;
    }

    private function generateService(string $label, array $service, bool $diffOnly, Report $report): void
    {
        $registryPath = $this->root.'/'.$service['registry'];

        if (! is_file($registryPath)) {
            $report->problem("레지스트리를 찾을 수 없습니다: {$service['registry']}");

            return;
        }

        $registry = json_decode((string) file_get_contents($registryPath), true);
        $selected = $this->select($registry, $service, $label, $report);

        $dir = $this->root.'/'.$service['path'];
        $planned = [];

        foreach ($selected as $api => $spec) {
            $class = $this->className($api, $service['prefix']);
            $file = $dir.'/'.$class.'.php';
            $methods = $this->methods($spec);
            $planned[$class] = $api;

            if (is_file($file)) {
                $this->compare($file, $api, $class, $methods, $report);

                continue;
            }

            $report->created($service['path'].'/'.$class.'.php', $api);

            if (! $diffOnly) {
                if (! is_dir($dir)) {
                    mkdir($dir, 0o755, true);
                }
                file_put_contents($file, $this->renderApi($service['namespace'], $class, $api, $methods, $spec));
            }
        }

        if (! empty($service['service'])) {
            $this->updateService($service, $planned, $diffOnly, $report);
        }
    }

    /**
     * 설정의 include/exclude 를 적용한다.
     */
    private function select(array $registry, array $service, string $label, Report $report): array
    {
        $include = $service['include'] ?? 'all';
        $exclude = $service['exclude'] ?? [];

        if ($include === 'all') {
            $names = array_keys($registry);
        } else {
            $names = $include;

            foreach ($names as $name) {
                if (! isset($registry[$name])) {
                    $report->problem("[{$label}] 레지스트리에 없는 API 를 include 했습니다: {$name}");
                }
            }

            // include 로 고른 것 외에는 만들지 않는다. 무엇이 빠졌는지 알려는 준다.
            $skipped = count($registry) - count(array_intersect($names, array_keys($registry)));
            if ($skipped > 0) {
                $report->note("[{$label}] 레지스트리 {$skipped}개는 include 에 없어 건너뜁니다.");
            }
        }

        $names = array_diff($names, $exclude);

        return array_intersect_key($registry, array_flip($names));
    }

    /**
     * `SYNO.Chat.Channel.Member` → `ChannelMember`
     */
    private function className(string $api, string $prefix): string
    {
        $rest = trim(substr($api, strlen(rtrim($prefix, '.'))), '.');

        // `SYNO.Personal.Profile` 처럼 접두사와 이름이 같은 경우가 있다.
        if ($rest === '') {
            $segments = explode('.', rtrim($prefix, '.'));
            $rest = end($segments);
        }

        $class = str_replace('.', '', $rest);

        return in_array(strtolower($class), self::RESERVED, true) ? $class.'_' : $class;
    }

    /**
     * `ChannelMember` → `channel_member`, `MD5` → `md5`, `List_` → `list`
     */
    private function apiKey(string $class): string
    {
        $key = rtrim($class, '_');
        $key = preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '_', $key);
        $key = preg_replace('/(?<=[A-Z])(?=[A-Z][a-z])/', '_', (string) $key);

        return strtolower((string) $key);
    }

    /**
     * 메서드마다 그 메서드를 선언한 **가장 높은 버전**을 고른다.
     *
     * `SYNO.API.*` 는 같은 메서드를 대소문자만 바꿔 두 번 광고한다
     * (`Query`/`query`, `Login`/`login`, `Logout`/`logout`). 레지스트리 10개를 전부
     * 훑어 보면 대문자로 시작하는 메서드는 이 셋뿐이고 전부 소문자 짝이 있으므로,
     * 소문자 짝이 있는 쪽은 버린다. 안 그러면 `@method mixed Query()` 같은 게
     * 생성물에 섞여 든다.
     *
     * @return array<string, int>
     */
    private function methods(array $spec): array
    {
        $methods = [];

        $versions = $spec['methods'] ?? [];
        ksort($versions, SORT_NUMERIC);

        foreach ($versions as $version => $entries) {
            foreach ($entries as $entry) {
                foreach (array_keys($entry) as $name) {
                    $methods[$name] = (int) $version;
                }
            }
        }

        foreach (array_keys($methods) as $name) {
            if ($name !== strtolower($name) && isset($methods[strtolower($name)])) {
                unset($methods[$name]);
            }
        }

        ksort($methods);

        return $methods;
    }

    /**
     * 이미 있는 파일과 레지스트리를 비교해 차이만 리포트한다.
     *
     * @param  array<string, int>  $registryMethods
     */
    private function compare(string $file, string $api, string $class, array $registryMethods, Report $report): void
    {
        $source = (string) file_get_contents($file);
        $declared = $this->declaredMethods($source);

        $missing = array_diff_key($registryMethods, $declared);
        $extra = array_diff_key($declared, $registryMethods);
        $pinned = [];

        foreach ($declared as $name => $version) {
            if (isset($registryMethods[$name]) && $registryMethods[$name] !== $version) {
                $pinned[$name] = "{$version} → {$registryMethods[$name]}";
            }
        }

        if ($missing || $extra || $pinned) {
            $report->drift($api, $class, $missing, $extra, $pinned);
        }
    }

    /**
     * @return array<string, int>
     */
    private function declaredMethods(string $source): array
    {
        if (! preg_match('/\$methods\s*=\s*\[(.*?)\];/s', $source, $m)) {
            return [];
        }

        preg_match_all("/'([^']+)'\s*=>\s*(\d+)/", $m[1], $pairs, PREG_SET_ORDER);

        $methods = [];
        foreach ($pairs as $pair) {
            $methods[$pair[1]] = (int) $pair[2];
        }

        return $methods;
    }

    /**
     * @param  array<string, int>  $methods
     */
    private function renderApi(string $namespace, string $class, string $api, array $methods, array $spec): string
    {
        $docMethods = '';
        foreach (array_keys($methods) as $name) {
            $docMethods .= " * @method mixed {$name}(array \$params = [])\n";
        }

        $entries = '';
        foreach ($methods as $name => $version) {
            $entries .= "        '{$name}' => {$version},\n";
        }

        // authLevel 0 은 세션 없이 부르는 API 다(봇 토큰 인증, 로그인 이전 조회 등).
        // API_NAME 과 같은 성격(레지스트리에서 온 사실)이라 상수로 나란히 놓는다.
        $auth = ($spec['authLevel'] ?? 1) === 0
            ? "\n    // authLevel 0 — 세션을 쓰지 않는다.\n    const AUTH = false;\n"
            : '';

        $body = $entries === ''
            ? "    /** @var array<string, int> */\n    protected array \$methods = [];\n"
            : "    /** @var array<string, int> */\n    protected array \$methods = [\n{$entries}    ];\n";

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$namespace};

        use Sejongtf\\Synology\\Api;

        /**
         * {$api}
         *
         * tools/generate-apis.php 가 레지스트리에서 만든 뼈대다. 레지스트리에는 파라미터
         * 시그니처가 없으므로 메서드는 매직 호출(`\$api->method([...])`)로 쓴다.
         * 파라미터를 명시하고 싶으면 이 파일에 직접 메서드를 적으면 된다 — 한 번 있는 파일은
         * 생성기가 다시 건드리지 않는다.
         *
        {$docMethods} */
        class {$class} extends Api
        {
            const API_NAME = '{$api}';
        {$auth}
        {$body}}

        PHP;
    }

    /**
     * Service 의 `@property` 독블럭과 `$apis` 맵을 다시 만든다.
     *
     * 디렉터리에 실제로 있는 클래스에서 만들기 때문에, 손으로 추가한 API 도 살아남고
     * 독블럭 누락 같은 드리프트도 저절로 고쳐진다.
     */
    private function updateService(array $service, array $planned, bool $diffOnly, Report $report): void
    {
        $file = $this->root.'/'.$service['service'];

        if (! is_file($file)) {
            $report->problem("Service 파일을 찾을 수 없습니다: {$service['service']}");

            return;
        }

        $classes = $this->existingClasses($this->root.'/'.$service['path']);

        foreach (array_keys($planned) as $class) {
            $classes[$class] = true;
        }

        ksort($classes);

        $source = (string) file_get_contents($file);
        $serviceNamespace = $this->namespaceOf($source);
        $relative = trim(substr($service['namespace'], strlen($serviceNamespace)), '\\');

        $properties = '';
        $entries = '';

        foreach (array_keys($classes) as $class) {
            $key = $this->apiKey($class);
            $properties .= " * @property \\{$service['namespace']}\\{$class} \${$key}\n";
            $entries .= "        '{$key}' => {$relative}\\{$class}::class,\n";
        }

        $updated = preg_replace(
            '/(\/\*\*\n)(?: \* @property [^\n]*\n)+( \*\/)/',
            "$1{$properties}$2",
            $source,
            1,
        );

        $updated = preg_replace(
            '/(protected array \$apis = \[\n).*?(    \];)/s',
            "$1{$entries}$2",
            (string) $updated,
            1,
        );

        if ($updated !== $source) {
            $report->serviceUpdated($service['service'], count($classes));

            if (! $diffOnly) {
                file_put_contents($file, $updated);
            }
        }
    }

    /**
     * @return array<string, true>
     */
    private function existingClasses(string $dir): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $classes = [];

        foreach (glob($dir.'/*.php') ?: [] as $file) {
            $classes[basename($file, '.php')] = true;
        }

        return $classes;
    }

    private function namespaceOf(string $source): string
    {
        preg_match('/^namespace\s+([^;]+);/m', $source, $m);

        return $m[1] ?? '';
    }
}
