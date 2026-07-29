<?php

declare(strict_types=1);

/**
 * resources/registry/*.lib.json → Api 클래스 스켈레톤.
 *
 *   php tools/generate-apis.php          생성/갱신
 *   php tools/generate-apis.php --diff   무엇이 달라지는지만 보여주고 쓰지 않음
 *
 * 레지스트리에 있는 것: API 이름, 버전, 메서드 이름, authLevel.
 * 레지스트리에 **없는** 것: 파라미터 시그니처. 그래서 생성물은 `$methods` 맵과
 * `@method` 독블럭까지이고, 파라미터를 손으로 적고 싶으면 그 파일을 직접 고치면 된다.
 *
 * **이미 있는 파일은 절대 덮어쓰지 않는다.** 손으로 다듬은 내용이 날아가면 안 되고,
 * 기존 클래스의 버전 핀은 의도적인 경우가 많다(예: SYNO.Chat.Channel::enter 는
 * 레지스트리상 v5 지만 코드는 v2 로 고정돼 있다). 차이는 --diff 가 리포트한다.
 */

$root = dirname(__DIR__);
$diffOnly = in_array('--diff', $argv, true);

require_once $root.'/tools/lib/Report.php';
require_once $root.'/tools/lib/ApiGenerator.php';

$generator = new ApiGenerator($root, require $root.'/tools/apis.php');
$report = $generator->run($diffOnly);

exit($report->exitCode($diffOnly));
