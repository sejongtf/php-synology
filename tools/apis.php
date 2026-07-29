<?php

declare(strict_types=1);

/**
 * 코드젠 대상 설정.
 *
 * `tools/generate-apis.php` 가 읽는다. 레지스트리에 선언된 API 전부를 만들지 않는 이유는,
 * 관리자 내부 API 처럼 쓸 일이 없는 것까지 클래스로 만들면 찾기만 어려워지기 때문이다.
 *
 * 각 서비스 항목:
 *   prefix     레지스트리 API 이름의 접두사. 클래스 이름을 만들 때 잘라낸다.
 *   namespace  생성될 클래스의 네임스페이스
 *   path       생성될 파일의 디렉터리
 *   service    $apis 맵을 갱신할 Service 클래스 파일 (없으면 생략)
 *   include    'all' 이거나 만들 API 이름 배열
 *   exclude    'all' 일 때 제외할 API 이름 배열
 */

return [
    /**
     * `SYNO.API.*` 는 서비스가 아니라 프로토콜 자체다 — Service 로 묶지 않고
     * `Authenticator` 와 `Synology` 가 직접 쓴다. 그래서 'service' 키가 없다.
     *
     * 두 클래스 다 손으로 쓴 것이고 생성기는 기존 파일을 건드리지 않으므로,
     * 여기 등록하는 목적은 생성이 아니라 **대조**다. 로그인 버전 선택처럼
     * 어긋나면 곤란한 것들을 `--diff` 가 계속 지켜본다.
     */
    'Api.Auth' => [
        'prefix' => 'SYNO.API.',
        'namespace' => 'Sejongtf\\Synology\\Services\\Api',
        'path' => 'src/Services/Api',
        'registry' => 'resources/registry/SYNO.API.Auth.lib.json',
        // 나머지 다섯은 OIDC 리다이렉트·로그인 화면 설정·API 키 같은 DSM 내부용이다.
        'include' => ['SYNO.API.Auth'],
    ],

    'Api.Info' => [
        'prefix' => 'SYNO.API.',
        'namespace' => 'Sejongtf\\Synology\\Services\\Api',
        'path' => 'src/Services/Api',
        'registry' => 'resources/registry/SYNO.API.Info.lib.json',
        'include' => 'all',
    ],

    'Calendar' => [
        'prefix' => 'SYNO.Cal.',
        'namespace' => 'Sejongtf\\Synology\\Services\\Calendar\\Api',
        'path' => 'src/Services/Calendar/Api',
        'service' => 'src/Services/Calendar/Calendar.php',
        'registry' => 'resources/registry/SYNO.Cal.lib.json',
        'include' => 'all',
    ],

    'Chat' => [
        'prefix' => 'SYNO.Chat.',
        'namespace' => 'Sejongtf\\Synology\\Services\\Chat\\Api',
        'path' => 'src/Services/Chat/Api',
        'service' => 'src/Services/Chat/Chat.php',
        'registry' => 'resources/registry/SYNO.Chat.lib.json',
        'include' => 'all',
    ],

    'Contacts' => [
        'prefix' => 'SYNO.Contacts.',
        'namespace' => 'Sejongtf\\Synology\\Services\\Contacts\\Api',
        'path' => 'src/Services/Contacts/Api',
        'service' => 'src/Services/Contacts/Contacts.php',
        'registry' => 'resources/registry/SYNO.Contacts.lib.json',
        'include' => 'all',
    ],

    /**
     * MailPlusServer 는 65개 중 대부분이 Cluster/Diagnosis/Audit 같은 관리자 내부 API 라
     * 필요한 것만 골라 쓴다. 필요해지면 여기에 이름을 추가하면 된다.
     */
    'MailPlusServer' => [
        'prefix' => 'SYNO.MailPlusServer.',
        'namespace' => 'Sejongtf\\Synology\\Services\\MailPlusServer\\Api',
        'path' => 'src/Services/MailPlusServer/Api',
        'service' => 'src/Services/MailPlusServer/MailPlusServer.php',
        'registry' => 'resources/registry/SYNO.MailPlusServer.lib.json',
        'include' => [
            'SYNO.MailPlusServer.Account.Detail',
            'SYNO.MailPlusServer.Account.Quota',
            'SYNO.MailPlusServer.Delegation',
            'SYNO.MailPlusServer.Log',
            'SYNO.MailPlusServer.Log.Mail',
            'SYNO.MailPlusServer.Personal.AutoReply',
            'SYNO.MailPlusServer.Personal.Forward',
        ],
    ],

    'Personal.Application' => [
        'prefix' => 'SYNO.Personal.Application.',
        'namespace' => 'Sejongtf\\Synology\\Services\\Personal\\Api\\Application',
        'path' => 'src/Services/Personal/Api/Application',
        'service' => 'src/Services/Personal/Application.php',
        'registry' => 'resources/registry/SYNO.Personal.Application.lib.json',
        'include' => 'all',
    ],

    'Personal.MailAccount' => [
        'prefix' => 'SYNO.Personal.MailAccount',
        'namespace' => 'Sejongtf\\Synology\\Services\\Personal\\Api\\MailAccount',
        'path' => 'src/Services/Personal/Api/MailAccount',
        'service' => 'src/Services/Personal/MailAccount.php',
        'registry' => 'resources/registry/SYNO.Personal.MailAccount.lib.json',
        'include' => 'all',
    ],

    'Personal.Notification' => [
        'prefix' => 'SYNO.Personal.Notification.',
        'namespace' => 'Sejongtf\\Synology\\Services\\Personal\\Api\\Notification',
        'path' => 'src/Services/Personal/Api/Notification',
        'service' => 'src/Services/Personal/Notification.php',
        'registry' => 'resources/registry/SYNO.Personal.Notification.lib.json',
        'include' => 'all',
    ],

    'Personal.Profile' => [
        'prefix' => 'SYNO.Personal.Profile',
        'namespace' => 'Sejongtf\\Synology\\Services\\Personal\\Api\\Profile',
        'path' => 'src/Services/Personal/Api/Profile',
        'service' => 'src/Services/Personal/Profile.php',
        'registry' => 'resources/registry/SYNO.Personal.Profile.lib.json',
        'include' => 'all',
    ],
];
