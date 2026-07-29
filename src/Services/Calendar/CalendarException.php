<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Calendar;

use Sejongtf\Synology\Exceptions\FileOperationException;

class CalendarException extends FileOperationException
{
    /**
     * FileStation 과 코드 집합은 같지만 문구가 다르다. 두 문서가 각자 다르게 적어 뒀고
     * 여기 것은 Calendar 가이드를 따른 것이라 그대로 둔다.
     *
     * @link  https://global.synologydownload.com/download/Document/Software/DeveloperGuide/Package/Calendar/2.4/enu/Synology_Calendar_API_Guide_enu.pdf
     */
    const ERROR_CODES = [
        400 => 'Invalid parameter of file operation',
        401 => 'Unknown error of file operation',
        402 => 'System is too busy',
        403 => 'This user does not have permission to execute this operation',
        404 => 'This group does not have permission to execute this operation',
        405 => 'This user/group does not have permission to execute this operation',
        406 => 'Cannot obtain user/group information from the account server',
        407 => 'Operation not permitted',
        408 => 'No such file or directory',
        409 => 'File system not supported',
        410 => 'Failed to connect internet-based file system (ex: CIFS)',
        411 => 'Read-only file system',
        412 => 'Filename too long in the non-encrypted file system',
        413 => 'Filename too long in the encrypted file system',
        414 => 'File already exists',
        415 => 'Disk quota exceeded',
        416 => 'No space left on device',
        417 => 'Input/output error',
        418 => 'Illegal name or path',
        419 => 'Illegal file name',
        420 => 'Illegal file name on FAT file system',
        421 => 'Device or resource busy',
        599 => 'No such task of the file operation',
    ];
}
