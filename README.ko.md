# sejongtf/synology

[English](README.md) · **한국어**

[![tests](https://img.shields.io/github/actions/workflow/status/sejongtf/php-synology/tests.yml?branch=0.x&label=tests)](https://github.com/sejongtf/php-synology/actions/workflows/tests.yml)
[![packagist](https://img.shields.io/packagist/v/sejongtf/synology)](https://packagist.org/packages/sejongtf/synology)
[![php](https://img.shields.io/packagist/dependency-v/sejongtf/synology/php)](composer.json)
[![license](https://img.shields.io/packagist/l/sejongtf/synology)](LICENSE)

Synology DSM Web API 를 감싸는 PHP 클라이언트입니다. 프레임워크에 묶이지 않고, HTTP 전송
구현체도 고르지 않습니다.

```php
$syno = Synology::withSession('https://nas:5001', $sid);

$syno->contacts->contact->list(['addressbook_id' => 1]);
$syno->chat->channel->list();
```

## 설치

**HTTP 구현체를 반드시 같이 설치해야 합니다.**

```bash
composer require sejongtf/synology guzzlehttp/guzzle php-http/discovery
```

PHP 8.2 이상이 필요합니다. 런타임 의존성은 PSR 인터페이스 세 개(`psr/http-message`,
`psr/http-client`, `psr/http-factory`)뿐이고 **HTTP 구현체는 들어 있지 않습니다.** 위 명령의
Guzzle 이 그 자리를 채웁니다 — 하나로 PSR-18 클라이언트(`GuzzleHttp\Client`)와 PSR-17 팩토리
(`GuzzleHttp\Psr7\HttpFactory`)가 같이 들어옵니다. 더 가벼운 조합을 원하면 Guzzle 자리에
`symfony/http-client nyholm/psr7` 를 넣으면 됩니다.

`php-http/discovery` 는 **설치된 구현을 찾아 줄 뿐 구현을 제공하지 않습니다.** 구현체 없이
이것만 넣으면 설치는 조용히 되고, `Synology` 를 **만드는 순간**
`Http\Discovery\Exception\NotFoundException` 이 납니다(탐색은 첫 요청이 아니라
`withSession()`/`withStore()`/`connect()` 안에서 일어납니다).

### 자동 탐색을 쓰고 싶지 않다면

`php-http/discovery` 를 빼고 직접 넘기면 됩니다. 전부 선택 인자라, 넘기면 탐색을 건너뜁니다.

```php
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;

$syno = Synology::withSession($url, $sid, new Client, new HttpFactory);
```

요청 본문을 만들어야 하므로 PSR-17 **스트림** 팩토리도 필요합니다. Guzzle 의
`HttpFactory` 처럼 한 클래스가 요청 팩토리와 스트림 팩토리를 겸하는 경우가 대부분이라
위처럼 두 개만 넘겨도 됩니다. 갈라져 있는 구현체라면 마지막 자리에 따로 주세요.

```php
$syno = Synology::withSession($url, $sid, $http, $requestFactory, $streamFactory);
```

직접 넘기지도 않고 `php-http/discovery` 도 없으면, 무엇을 설치해야 하는지 알려 주는
`RuntimeException` 이 역시 생성 시점에 납니다.

## 세션 얻기

세 가지 방법이 있고 **셋 다 동등합니다. 로그인은 그중 하나일 뿐입니다.**

### 이미 가진 sid 주입하기

로그인 요청이 한 번도 나가지 않습니다. sid 의 출처는 묻지 않습니다.

```php
$syno = Synology::withSession($url, $sid);
$syno = Synology::withSession($url, new Session($sid, synoToken: $token));
```

### 외부 저장소에서 읽고 쓰기

```php
use Sejongtf\Synology\Auth\CallableStore;

$syno = Synology::withStore($url, new CallableStore(
    get: fn () => $cache->get('syno.session'),          // Session | array | sid 문자열 | null
    put: fn (Session $s) => $cache->set('syno.session', $s->toArray()),
));
```

`Contracts\SessionStore` 를 직접 구현해도 됩니다. 기본 구현으로 `InMemoryStore` 와
`CallableStore` 가 있습니다.

### 자격증명으로 로그인하기

```php
$syno = Synology::connect($url, 'user', 'pass', otpCode: '123456');
```

**실제 로그인은 첫 요청 때 일어납니다.** 서비스 컨테이너 부팅 중에 네트워크를 때리지 않기
위해서입니다.

세션이 만료되면(오류 106/107/119) **자격증명이 있을 때만** 한 번 다시 로그인하고 요청을
재시도합니다. sid 만 주입한 경우 라이브러리에는 다시 로그인할 방법이 없으므로 오류가 그대로
돌아옵니다 — 소비자가 받아서 직접 갱신하면 됩니다. 이 오류는 어느 API 에서 나오든
`AuthException` 계열이라 `catch (AuthException)` 하나로 처리됩니다.

### 2단계 인증

`otpCode` 는 **첫 로그인에서 한 번만** 쓰입니다. TOTP 코드는 일회용이라, 보내고 나면
받아들여졌든 거절당했든 버리고 이후 재로그인에는 싣지 않습니다. 다시 시도하려면 새 코드를
직접 넘기면 됩니다.

```php
try {
    $syno->contacts->contact->list(['addressbook_id' => 1]);
} catch (AuthException $e) {
    if ($e->requiresOtp()) {                       // 403/406
        $syno->authenticator()->login($codeFromUser);
    }
}
```

`rememberDevice: true` 로 로그인하면 응답에 device token(`did`)이 딸려 와
`$session->did` 에 담깁니다. **이후 로그인에는 라이브러리가 알아서 실어 줍니다** — 세션이
만료돼 다시 로그인할 때도 그 값을 쓰므로 OTP 를 다시 묻지 않습니다. 만료되는 건 세션이지
기기 등록이 아니기 때문입니다.

단, `did` 는 세션과 함께 저장소에 들어갑니다. 기본값인 프로세스 메모리 저장소로는 프로세스가
끝나면 같이 사라지므로, **요청 간에 유지하려면 세션을 저장소에 두거나**(`store:`)
`did` 를 따로 보관해 `deviceId` 로 넘겨야 합니다.

```php
$syno = Synology::connect($url, 'user', 'pass',
    otpCode: '123456',
    rememberDevice: true,
    store: $store,          // 이 저장소에 sid 와 did 가 함께 남는다
);
```

### 여러 프로세스가 세션 하나를 나눠 쓸 때

저장소를 공유하면 세션도 공유되지만, 만료도 같이 옵니다 — 알아챈 프로세스마다 다시
로그인하려 듭니다. DSM 은 같은 계정이 두 번 로그인하면 앞의 세션을 끊으므로(그게 107)
재로그인이 몰리면 서로가 서로를 무효화합니다.

재시도를 직접 가져가면 막을 수 있습니다. `Http\Connection::onSessionExpired()` 는 기본
콜백을 갈아끼우고, 콜백은 **방금 실패한 요청에 실려 있던 sid** 를 받습니다. 다른 쪽이 이미
갱신했는지 알려 주는 값은 그것뿐입니다. 저장소의 sid 가 그와 다르면 그 세션을 쓰면 됩니다.

```php
$connection = $syno->connection();
$auth = $syno->authenticator();

$connection->onSessionExpired(fn (string $staleSid) => $lock->block(5, function () use ($staleSid, $store, $auth) {
    $current = $store->get();

    // 잠금을 기다리는 사이에 다른 쪽이 갱신했다. 여기서 또 로그인하면
    // 그쪽 세션만 끊는 꼴이다.
    return $current && $current->sid !== $staleSid ? $current : $auth->refresh();
}));
```

**그 안에서 `$store->forget()` 을 부르면 안 됩니다.** 저장소를 비우는 건
`Authenticator::refresh()` 가 하고, 만료된 세션에서 device token 을 읽은 **다음**에 합니다.
미리 비워 두면 그 조회가 빈 저장소를 읽고, 2단계 인증이 강제된 계정에서는 재로그인이
OTP 를 요구해 실패합니다.

## 호출하기

`서비스 → API → 메서드` 세 단계입니다. 이름은 DSM 의 snake_case 를 그대로 따르고,
PHP 쪽에서 익숙한 camelCase 도 받습니다.

```php
$syno->mail_account->mail->send([...]);
$syno->mailAccount->mail->send([...]);   // 같다
```

`$methods` 에 선언된 DSM 메서드는 PHP 메서드가 없어도 매직 호출로 그냥 부를 수 있습니다.
파라미터가 많거나 자주 쓰는 것만 실제 시그니처가 채워져 있습니다.

```php
$syno->chat->channel->list();                       // 매직 호출
$syno->contacts->contact->get([1, 2, 3]);           // 손으로 채운 시그니처
```

세션이 필요 없는 API 도 있습니다. Chat 의 봇 토큰 계열이 그렇습니다.

```php
$syno->chat->external->incoming($token, '안녕하세요');
```

### 응답

API 는 응답 봉투의 `data` 를 **배열**로 돌려줍니다. `data` 키 자체가 없으면 `null` 이라
"성공했지만 데이터가 빈 것"(`[]`)과 구분됩니다.

```php
$data = $syno->calendar->event->list([...]);   // array|null
```

헤더나 원본 본문이 필요하거나, "성공했지만 데이터가 없음" 과 "데이터 키 자체가 없음" 을
구분해야 하면 `raw()` 로 응답 객체를 통째로 받으면 됩니다.

```php
$response = $syno->chat->post->raw('list', params: [...]);

$response->success();
$response->hasData();
$response->header('Content-Type');
$response->toPsrResponse()->getBody();   // 바이너리는 이쪽으로
```

**타입이 있는 엔티티 계층은 없습니다.** 날짜·소수 캐스팅이 필요하면 소비자 쪽에 두면 됩니다.

### 오류

**일반 호출은 실패해도 예외를 던지지 않습니다.** DSM 이 오류를 돌려주면 그냥 `null` 이
옵니다. 예외로 받고 싶으면 `raw()` 로 응답을 꺼내 `throw()` 를 부르면 됩니다.

```php
use Sejongtf\Synology\Exceptions\ApiException;
use Sejongtf\Synology\Exceptions\AuthException;

$syno->contacts->contact->list(['addressbook_id' => 1]);   // 실패하면 null

try {
    $data = $syno->contacts->contact
        ->raw('list', params: ['addressbook_id' => 1])
        ->throw()
        ->data();
} catch (AuthException $e) {
    // 로그인 실패, 2단계 인증 필요, 세션 만료(106/107/119)
} catch (ApiException $e) {
    // 그 밖의 DSM 오류
    $e->getErrorCode();   // DSM 오류 코드. getCode() 가 아니다
    $e->response;         // PSR-7 응답
}
```

DSM 은 오류 코드 표를 여러 벌 쓰고 숫자가 겹칩니다(로그인 400 과 파일 연산 400 은 다른
오류입니다). 그래서 예외 클래스가 표 단위로 나뉘어 있습니다.

| 예외 | 표 | 언제 |
|---|---|---|
| `ApiException` | 공통 100–160 | 기본값 |
| `AuthException` | 로그인 400–410, 그리고 세션 만료 106/107/119 | `SYNO.API.Auth`, 그리고 어느 API 든 106/107/119 |
| `FileOperationException` | 파일 연산 400–421, 599 | `CalendarException` 의 부모. 직접 선택되지는 않습니다 |
| `Services\Calendar\CalendarException` | 같은 코드에 Calendar 가이드 문구 | `SYNO.Cal.*` |
| `TransportException` | — | 응답이 오기 전 실패(DNS, 연결 거부, TLS) |

`ApiException` 계열은 전부 `RequestException` 을 상속하고 `$e->response` 로 PSR-7 응답을
들고 있습니다. `TransportException` 만 그 밖에 있습니다 — 그 단계에는 응답 자체가 없습니다.

## 지원하는 서비스

| 서비스 | 접근 | API 수 |
|---|---|---|
| Chat | `$syno->chat` | 36 |
| Calendar | `$syno->calendar` | 16 |
| Contacts | `$syno->contacts` | 7 |
| Core | `$syno->core` | 3 |
| MailPlusServer | `$syno->mail_plus_server` | 14 |
| Personal | `$syno->application` `$syno->mail_account` `$syno->notification` `$syno->profile` | 16 |

`resources/registry/` 에 DSM 이 실제로 광고하는 API 목록(이름·버전·메서드)이 덤프돼
있습니다. 필요한 API 가 빠져 있으면 `tools/generate-apis.php` 로 클래스를 생성할 수 있습니다.
MailPlusServer 는 65개 중 대부분이 관리자용 내부 API 라 쓰는 것만 골라 두었습니다.

**이 덤프는 특정 시점·특정 NAS 의 스냅샷입니다.** 파일마다 뜬 시기가 다르고 어느 DSM
버전인지도 적혀 있지 않습니다. DSM 이 올라가면 API 버전이 새로 생기기도 하고, 이름이 같은
메서드의 파라미터 의미가 버전에 따라 달라지기도 합니다. 그러니 여기 적힌 버전 범위를
계약처럼 믿지 말고, 중요한 호출이라면 아래 디스커버리로 쓰는 NAS 에 직접 확인하는 편이
안전합니다.

각 API 클래스가 보내는 버전은 클래스에 적힌 값으로 **고정**돼 있습니다. 일부러 낮게 묶어 둔
것도 있습니다 — 예를 들어 `SYNO.API.Auth` 는 7 이 있어도 6 으로 보냅니다. 7 에서 `token`
메서드가 없어졌기 때문입니다. 다른 버전이 필요하면 호출할 때 직접 지정하면 됩니다.

```php
$syno->chat->channel->request('list', version: 5, params: [...]);
```

## API 디스커버리

DSM 7 은 대부분의 API 를 `entry.cgi` 하나로 받지만 전부는 아닙니다. 경로와 버전 범위를
서버에서 확인하려면:

```php
$syno->discover();          // 전체
$syno->discover('SYNO.Chat.Channel');
```

하지 않아도 동작합니다 — 그 경우 전부 `entry.cgi` 기본값으로 갑니다.

**버전은 알려만 주고 바꾸지 않습니다.** 클래스에 박힌 버전 핀에는 이유가 있어서, 서버가 더
높은 버전을 광고한다고 그리로 옮기는 건 안전한 동작이 아닙니다.

경로는 **기본 `Http\Connection` 을 쓸 때만** 자동으로 반영됩니다. `Contracts\Connection` 을
직접 구현해 넘겼다면 `discover()` 는 조회해서 돌려주기만 하고 아무것도 반영하지 않습니다 —
반영은 그 구현이 할 일입니다. 어느 쪽이든 반환된 `ApiRegistry` 로 쓰는 NAS 가 실제로 뭘
지원하는지 읽을 수 있습니다.

```php
$registry = $syno->discover('SYNO.Chat.Channel');

$registry->maxVersion('SYNO.Chat.Channel');   // 이 NAS 가 광고하는 최대 버전
$registry->path('SYNO.Chat.Channel');         // 실제 경로
```

## 전송 방식

요청은 **POST** 로 나가고 요청 내용은 전부 `application/x-www-form-urlencoded` 본문에
담깁니다. URL 에 남는 건 `SynoToken` 하나뿐이고, 그것도 세션에 토큰이 있을 때뿐입니다.

```
POST /webapi/entry.cgi

api=SYNO.Contacts.Contact&version=2&method=list&addressbook_id=3&_sid=…
```

`account` `passwd` `otp_code` `_sid` 와 본문 파라미터가 URL 에서 빠지므로 NAS access log
나 리버스 프록시 로그에 평문으로 남지 않습니다. URL 길이 제한(실측 약 8KB)도 걸리지
않습니다. 대신 NAS 쪽 로그에는 어느 API 를 불렀는지 남지 않습니다.

**파일 업로드는 지원하지 않습니다.** DSM 의 업로드 API 는 `multipart/form-data` 를 쓰는데
이 패키지에는 멀티파트 전송이 없습니다. 그래서 `SYNO.Personal.Profile.Photo` 의 `upload`
처럼 DSM 이 광고하는 업로드 메서드는 일부러 빼 두었습니다 — 부르면
`BadMethodCallException` 이 납니다.

`Contracts\Connection` 을 직접 구현한다면 지켜야 할 것들이 있습니다. 앞의 둘은 DSM 의
제약이고, 뒤의 둘은 이 패키지와의 역할 분담입니다.

- `SynoToken` 은 **본문에 넣으면 안 됩니다.** CSRF 검사가 쿼리스트링과 `X-SYNO-TOKEN`
  헤더만 읽어서 119(SID not found)로 실패합니다.
- 본문이 빈 POST 는 라우팅이 쿼리에 있어도 오류 101 로 거절됩니다. `api` `version`
  `method` 를 본문에 두면 인자 없는 호출에서도 본문이 비지 않습니다.
- 넘어오는 `$params` 에서 **배열은 이미 JSON 문자열입니다**(`Api::raw()` 가 합니다). 다시
  인코딩하면 이중 인코딩이 됩니다. 반대로 bool 과 null 은 PHP 값 그대로 올 수 있으므로
  구현이 처리해야 합니다 — bool 은 `'true'`/`'false'` 문자열로, null 은 아예 빼는 게
  맞습니다(`http_build_query` 에 그냥 넘기면 `1`/`0` 이 되어 DSM 이 잘못 읽습니다).
- `_sid` 는 `Api::raw()` 가 넣어 주고, 세션 만료 재시도는 구현 몫입니다. 기본
  `Http\Connection` 은 `onSessionExpired()` 콜백이 있을 때만 한 번 재시도하고, 그 콜백에
  방금 만료된 sid 를 넘깁니다 — 저장소를 공유할 때 다른 프로세스가 이미 갈아끼운 세션인지
  구분하라고 있는 값입니다.

## 테스트

```bash
composer install
composer test
```

기본 테스트 스위트는 네트워크를 타지 않습니다. 실기기를 상대로 도는 통합 테스트는
`phpunit.xml.dist` 를 `phpunit.xml` 로 복사해 `SYNOLOGY_*` 값을 채워야 돌아갑니다
(`phpunit.xml` 은 gitignore 대상이라 자격증명이 밖으로 나가지 않습니다).

```bash
composer test:integration
```

통합 테스트는 전부 읽기 전용입니다.

CI 는 PHP 8.2·8.3·8.4 에서 단위 테스트를 돌리고, 최저 의존성으로도 한 번 더 돌립니다
(`composer.lock` 은 커밋하지 않으므로 매번 새로 풉니다). 그 밖에 `composer validate --strict`,
문법 검사, 코드젠 멱등성을 확인합니다.

## 그 밖에

코드를 고칠 거라면 [CLAUDE.md](CLAUDE.md) 에 구조와 설계 의도가 정리돼 있습니다.

## 라이선스

MIT.
