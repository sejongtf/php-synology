<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\MailPlusServer\Api;

use Sejongtf\Synology\Api;

class Version extends Api
{
    const API_NAME = 'SYNO.MailPlusServer.Version';

    /** @var array<string, int> */
    protected array $methods = [
        'set' => 1,
        'check' => 1,
    ];

    public function check()
    {
        return $this->request('check', 1);
    }
}
