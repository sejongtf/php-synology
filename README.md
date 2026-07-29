# sejongtf/synology

**English** · [한국어](README.ko.md)

[![tests](https://img.shields.io/github/actions/workflow/status/sejongtf/php-synology/tests.yml?branch=0.x&label=tests)](https://github.com/sejongtf/php-synology/actions/workflows/tests.yml)
[![packagist](https://img.shields.io/packagist/v/sejongtf/synology)](https://packagist.org/packages/sejongtf/synology)
[![php](https://img.shields.io/packagist/dependency-v/sejongtf/synology/php)](composer.json)
[![license](https://img.shields.io/packagist/l/sejongtf/synology)](LICENSE)

A PHP client for the Synology DSM Web API. It is tied to no framework, and it picks no HTTP
transport for you.

```php
$syno = Synology::withSession('https://nas:5001', $sid);

$syno->contacts->contact->list(['addressbook_id' => 1]);
$syno->chat->channel->list();
```

## Installation

**Install an HTTP implementation alongside it.**

```bash
composer require sejongtf/synology guzzlehttp/guzzle php-http/discovery
```

PHP 8.2+. The runtime dependencies are three PSR interface packages (`psr/http-message`,
`psr/http-client`, `psr/http-factory`) and **no HTTP implementation is bundled.** Guzzle fills
that slot in the command above — one package brings both the PSR-18 client
(`GuzzleHttp\Client`) and the PSR-17 factory (`GuzzleHttp\Psr7\HttpFactory`). For a lighter
combination, put `symfony/http-client nyholm/psr7` where Guzzle is.

`php-http/discovery` **only finds an installed implementation; it does not provide one.**
Installing it without an implementation succeeds quietly, and then
`Http\Discovery\Exception\NotFoundException` is thrown the moment you **construct** `Synology`
— discovery runs inside `withSession()`/`withStore()`/`connect()`, not on the first request.

### If you would rather not use discovery

Drop `php-http/discovery` and pass the implementations yourself. They are all optional
arguments, and passing them skips discovery.

```php
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;

$syno = Synology::withSession($url, $sid, new Client, new HttpFactory);
```

Building the request body needs a PSR-17 **stream** factory too. In most implementations a
single class serves as both the request factory and the stream factory — Guzzle's `HttpFactory`
does — so the two arguments above are usually enough. If yours are separate classes, pass the
stream factory last.

```php
$syno = Synology::withSession($url, $sid, $http, $requestFactory, $streamFactory);
```

If you pass nothing and `php-http/discovery` is absent, a `RuntimeException` naming what to
install is thrown, again at construction time.

## Getting a session

There are three ways, and **all three are equal. Logging in is merely one of them.**

### Inject a sid you already have

No login request is ever sent. Where the sid came from is not this library's business.

```php
$syno = Synology::withSession($url, $sid);
$syno = Synology::withSession($url, new Session($sid, synoToken: $token));
```

### Read and write it through your own store

```php
use Sejongtf\Synology\Auth\CallableStore;

$syno = Synology::withStore($url, new CallableStore(
    get: fn () => $cache->get('syno.session'),          // Session | array | sid string | null
    put: fn (Session $s) => $cache->set('syno.session', $s->toArray()),
));
```

You can implement `Contracts\SessionStore` directly. `InMemoryStore` and `CallableStore` ship
as the built-in implementations.

### Log in with credentials

```php
$syno = Synology::connect($url, 'user', 'pass', otpCode: '123456');
```

**The login actually happens on the first request**, so that booting a service container never
hits the network.

When a session expires (errors 106/107/119) the library logs in again once and retries the
request — **but only when it has credentials.** If you injected a bare sid there is no way for
it to log in again, so the error surfaces as-is and refreshing is yours to do. Whichever API
raised it, that error is an `AuthException`, so a single `catch (AuthException)` covers it.

### Two-factor authentication

`otpCode` is used **once, on the first login.** A TOTP code is single-use, so it is discarded
after it succeeds and never replayed on a later login. To retry with a fresh code, pass one in.

```php
try {
    $syno->contacts->contact->list(['addressbook_id' => 1]);
} catch (AuthException $e) {
    if ($e->requiresOtp()) {                       // 403/406
        $syno->authenticator()->login($codeFromUser);
    }
}
```

Logging in with `rememberDevice: true` returns a device token (`did`) in the response, carried
on `$session->did`. **The library sends it on subsequent logins for you** — including the
re-login after an expiry, so you are not asked for an OTP again. What expires is the session,
not the device registration.

The `did` is stored with the session, though. The default store lives in process memory and
dies with the process, so to keep it **across requests either put the session in a store**
(`store:`) or keep the `did` yourself and pass it as `deviceId`.

```php
$syno = Synology::connect($url, 'user', 'pass',
    otpCode: '123456',
    rememberDevice: true,
    store: $store,          // sid and did both land in this store
);
```

## Making calls

Three steps: `service → API → method`. Names follow DSM's snake_case as-is, and the camelCase
that reads naturally in PHP is accepted too.

```php
$syno->mail_account->mail->send([...]);
$syno->mailAccount->mail->send([...]);   // the same
```

Any DSM method declared in `$methods` is callable through the magic call even without a PHP
method behind it. Real signatures are filled in only where a call takes many parameters or gets
used often.

```php
$syno->chat->channel->list();                       // magic call
$syno->contacts->contact->get([1, 2, 3]);           // hand-written signature
```

Some APIs need no session at all — Chat's bot-token family, for one.

```php
$syno->chat->external->incoming($token, 'Hello');
```

### Responses

An API returns the `data` of the response envelope as an **array**. When the `data` key is
absent altogether you get `null`, which distinguishes it from "succeeded with empty data"
(`[]`).

```php
$data = $syno->calendar->event->list([...]);   // array|null
```

When you need headers or the raw body, or you need to tell "succeeded with no data" from "no
data key at all", take the whole response object with `raw()`.

```php
$response = $syno->chat->post->raw('list', params: [...]);

$response->success();
$response->hasData();
$response->header('Content-Type');
$response->toPsrResponse()->getBody();   // binary goes through here
```

**There is no typed entity layer.** Casting dates and decimals belongs on the consumer side.

### Errors

**An ordinary call does not throw on failure.** When DSM returns an error you simply get
`null`. To get an exception instead, take the response with `raw()` and call `throw()`.

```php
use Sejongtf\Synology\Exceptions\ApiException;
use Sejongtf\Synology\Exceptions\AuthException;

$syno->contacts->contact->list(['addressbook_id' => 1]);   // null on failure

try {
    $data = $syno->contacts->contact
        ->raw('list', params: ['addressbook_id' => 1])
        ->throw()
        ->data();
} catch (AuthException $e) {
    // login failure, 2FA required, session expiry (106/107/119)
} catch (ApiException $e) {
    // any other DSM error
    $e->getErrorCode();   // the DSM error code — not getCode()
    $e->response;         // the PSR-7 response
}
```

DSM uses several error-code tables and the numbers collide (400 on login and 400 on a file
operation are different errors). That is why the exception classes are split one per table.

| Exception | Table | When |
|---|---|---|
| `ApiException` | common 100–160 | the default |
| `AuthException` | login 400–410, plus session expiry 106/107/119 | `SYNO.API.Auth`, and 106/107/119 from any API |
| `FileOperationException` | file operations 400–421, 599 | parent of `CalendarException`; never selected on its own |
| `Services\Calendar\CalendarException` | same codes, worded by the Calendar guide | `SYNO.Cal.*` |
| `TransportException` | — | failure before any response (DNS, refused connection, TLS) |

Every `ApiException` descends from `RequestException` and carries the PSR-7 response on
`$e->response`. Only `TransportException` sits outside that — at that stage there is no
response to hold.

## Supported services

| Service | Access | APIs |
|---|---|---|
| Chat | `$syno->chat` | 36 |
| Calendar | `$syno->calendar` | 16 |
| Contacts | `$syno->contacts` | 7 |
| Core | `$syno->core` | 3 |
| MailPlusServer | `$syno->mail_plus_server` | 14 |
| Personal | `$syno->application` `$syno->mail_account` `$syno->notification` `$syno->profile` | 16 |

`resources/registry/` holds dumps of the API list DSM actually advertises (names, versions,
methods). If an API you need is missing, `tools/generate-apis.php` can generate the class. Of
MailPlusServer's 65 APIs most are administrative internals, so only the ones in use are
included.

**Those dumps are a snapshot of one NAS at one moment.** The files were taken at different
times and none of them records which DSM version it came from. A DSM upgrade can add API
versions, and a method that keeps its name can change what its parameters mean from one version
to the next. So do not treat the version ranges here as a contract — for anything load-bearing,
confirm it against the NAS you actually talk to, using discovery below.

The version each API class sends is **pinned** to the value written in the class. Some are
deliberately held back — `SYNO.API.Auth` sends 6 even though 7 exists, because 7 dropped the
`token` method. When you need a different version, name it at the call site.

```php
$syno->chat->channel->request('list', version: 5, params: [...]);
```

## API discovery

DSM 7 serves most APIs through the single `entry.cgi`, but not all of them. To confirm paths
and version ranges against the server:

```php
$syno->discover();          // everything
$syno->discover('SYNO.Chat.Channel');
```

It works without this — everything then goes to the `entry.cgi` default.

**Versions are reported, never changed.** The version pins in the classes are there for a
reason, so moving to whatever higher version the server advertises is not a safe thing to do
automatically.

Paths are applied automatically **only when you use the shipped `Http\Connection`.** If you
passed your own implementation of `Contracts\Connection`, `discover()` merely fetches and
returns — applying the result is that implementation's job. Either way, the returned
`ApiRegistry` tells you what the NAS in front of you actually supports.

```php
$registry = $syno->discover('SYNO.Chat.Channel');

$registry->maxVersion('SYNO.Chat.Channel');   // the highest version this NAS advertises
$registry->path('SYNO.Chat.Channel');         // the real path
```

## On the wire

Requests go out as **POST** with everything in an `application/x-www-form-urlencoded` body. The
only thing left in the URL is `SynoToken`, and only when the session has one.

```
POST /webapi/entry.cgi

api=SYNO.Contacts.Contact&version=2&method=list&addressbook_id=3&_sid=…
```

Keeping `account`, `passwd`, `otp_code`, `_sid` and the body parameters out of the URL stops
them from being logged in the clear by the NAS access log or a reverse proxy. It also clears
the URL length ceiling (measured at roughly 8 KB). The cost is that NAS-side logs no longer
show which API was called.

**File upload is not supported.** DSM's upload APIs use `multipart/form-data` and this package
has no multipart transport, so upload methods DSM advertises — `SYNO.Personal.Profile.Photo`'s
`upload`, for one — are deliberately left out. Calling one raises `BadMethodCallException`.

Implementing `Contracts\Connection` yourself comes with obligations. The first two are DSM's
constraints; the last two are the division of labour with this package.

- `SynoToken` **must not go in the body.** The CSRF check reads only the query string and the
  `X-SYNO-TOKEN` header, so a token in the body fails with 119 (SID not found).
- A POST with an empty body is rejected with error 101 even when the routing keys sit in the
  query string. Keeping `api`, `version` and `method` in the body means even a parameterless
  call has a non-empty one.
- In the `$params` you receive, **arrays are already JSON strings** (`Api::raw()` does that).
  Encoding them again double-encodes. Bools and nulls, on the other hand, may arrive as plain
  PHP values and are yours to handle — bools as the strings `'true'`/`'false'`, nulls dropped
  entirely (handing a bool to `http_build_query` yields `1`/`0`, which DSM misreads).
- `_sid` is put there by `Api::raw()`, and retrying an expired session is up to the
  implementation. The shipped `Http\Connection` retries exactly once, and only when an
  `onSessionExpired()` callback was set.

## Tests

```bash
composer install
composer test
```

The default suite never touches the network. The integration tests run against real hardware
and require copying `phpunit.xml.dist` to `phpunit.xml` and filling in the `SYNOLOGY_*` values
(`phpunit.xml` is gitignored, so credentials stay local).

```bash
composer test:integration
```

Every integration test is read-only.

CI runs the unit tests on PHP 8.2/8.3/8.4 and once more against the lowest dependencies
(`composer.lock` is not committed, so every job resolves fresh). It also checks
`composer validate --strict`, PHP syntax, and codegen idempotency.

## Anything else

If you are going to change the code, [CLAUDE.md](CLAUDE.md) lays out the structure and the
design intent behind it.

## License

MIT.
