<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Tests;

use Nyholm\Psr7\Response as PsrResponse;
use PHPUnit\Framework\TestCase;
use Sejongtf\Synology\Exceptions\ApiException;
use Sejongtf\Synology\Exceptions\AuthException;
use Sejongtf\Synology\Exceptions\FileOperationException;
use Sejongtf\Synology\Services\Calendar\CalendarException;

class ErrorCodeTest extends TestCase
{
    private function psr(): PsrResponse
    {
        return new PsrResponse(200, ['Content-Type' => 'application/json'], '{"success":false}');
    }

    /**
     * 이게 이 파일에서 가장 중요한 테스트다. 400 은 문맥에 따라 뜻이 완전히 다르다.
     * 두 표를 하나로 합치면 조용히 틀린 메시지를 내보내게 된다.
     */
    public function test_400_means_different_things_in_auth_and_file_operations(): void
    {
        $auth = new AuthException($this->psr(), 400);
        $file = new FileOperationException($this->psr(), 400);

        $this->assertStringContainsString('No such account or incorrect password', $auth->getMessage());
        $this->assertStringContainsString('Invalid parameter of file operation', $file->getMessage());

        $this->assertSame(400, $auth->getErrorCode());
        $this->assertSame(400, $file->getErrorCode());
    }

    public function test_service_tables_take_precedence_over_the_common_table(): void
    {
        // 공통 표에도 없는 번호라 서비스 표에서만 찾을 수 있다.
        $this->assertStringContainsString('No such file or directory', (new FileOperationException($this->psr(), 408))->getMessage());

        // 서비스 표에 없으면 공통 표로 넘어간다.
        $this->assertStringContainsString('Session timeout', (new FileOperationException($this->psr(), 106))->getMessage());
    }

    public function test_unknown_codes_say_so(): void
    {
        $this->assertStringContainsString('Unknown error code: 9999', (new ApiException($this->psr(), 9999))->getMessage());
    }

    public function test_an_explicit_message_wins(): void
    {
        $this->assertSame('직접 지정', (new AuthException($this->psr(), 400, '직접 지정'))->getMessage());
    }

    public function test_file_station_and_calendar_share_the_hierarchy(): void
    {
        $this->assertInstanceOf(FileOperationException::class, new CalendarException($this->psr(), 408));
        $this->assertInstanceOf(ApiException::class, new CalendarException($this->psr(), 408));
    }

    /**
     * 두 문서가 같은 코드를 다르게 적어 뒀다. Calendar 쪽 문구를 임의로 버리지 않는다.
     */
    public function test_calendar_keeps_its_own_wording(): void
    {
        $calendar = new CalendarException($this->psr(), 403);
        $fileOperation = new FileOperationException($this->psr(), 403);

        $this->assertStringContainsString('This user does not have permission', $calendar->getMessage());
        $this->assertStringContainsString('Invalid user does this file operation', $fileOperation->getMessage());
    }

    public function test_auth_exception_classifies_otp_and_credential_failures(): void
    {
        $this->assertTrue((new AuthException($this->psr(), 403))->requiresOtp());
        $this->assertTrue((new AuthException($this->psr(), 406))->requiresOtp());
        $this->assertFalse((new AuthException($this->psr(), 400))->requiresOtp());

        $this->assertTrue((new AuthException($this->psr(), 400))->isCredentialFailure());
        $this->assertFalse((new AuthException($this->psr(), 403))->isCredentialFailure());
    }

}
