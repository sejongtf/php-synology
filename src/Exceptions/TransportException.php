<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Exceptions;

use RuntimeException;

/**
 * 응답을 받기도 전에 실패한 경우 (DNS, 연결 거부, TLS, 타임아웃 등).
 *
 * `RequestException` 은 PSR 응답을 들고 있어야 하는데 이 단계에는 응답이 없다.
 * PSR-18 구현이 던진 예외는 `getPrevious()` 로 꺼낼 수 있다.
 */
class TransportException extends RuntimeException
{
    //
}
