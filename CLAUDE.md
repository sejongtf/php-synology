# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

**`README.md` is the consumer-facing doc** — installation, the three ways to get a session, the calling convention, error handling. Read it for how the library is *used*; this file is only what you need to *change* it. When behaviour changes, both move.

`README.ko.md` is the Korean translation of `README.md` and the two are kept in sync **in the same commit** — English is the source, Korean follows it. Nothing links to it but `README.md`'s header line, so a translation left behind goes unnoticed. No other file changes language: code comments stay Korean (see Conventions), this file stays English.

## What this is

`sejongtf/synology` — a PHP 8.2+ Composer **library** (no application, no framework bootstrap) wrapping the Synology DSM Web API. PSR-4: `Sejongtf\Synology\` → `src/`.

It ships a PSR-18 implementation (`Http\Connection`) but **never picks a transport**, and there is no `illuminate/*`, no Laravel helper (`config()`, `app()`), no facade. `tests/DependencyGuardTest.php` enforces two rules that can still be broken: the `require` allowlist, and that `src/` never reaches for a dev-only package. Treat a failure there as a regression, not a test to relax. **The allowlist must stay interfaces-only**: a concrete implementation like `guzzlehttp/guzzle` appearing in it means the rule is broken. (The file used to also grep `src/` for `Illuminate`, `GuzzleHttp` and Laravel globals — those were one-time checks from the Laravel-era migration and are gone.)

`config.platform.php` is pinned to `8.2` in `composer.json` so dev tooling resolves against the package's minimum PHP. Without it Composer picks PHPUnit 13 (`php >=8.4.1`), which contributors on PHP 8.2/8.3 cannot install.

## Commands

```bash
composer install                                  # composer.lock is gitignored; vendor/ is local only
vendor/bin/phpunit                                # or: composer test
vendor/bin/phpunit --filter ExternalTest          # single test class
composer validate
php -l src/Path/To/File.php
php tools/generate-apis.php --diff                # what codegen would change
vendor/bin/phpunit --testsuite integration        # real NAS; skips unless configured
```

`phpunit.xml.dist` is tracked, `phpunit.xml` is gitignored, `defaultTestSuite="unit"` — a bare `vendor/bin/phpunit` never touches the network. No linter in the repo.

CI (`.github/workflows/tests.yml`) runs the unit suite on PHP 8.2/8.3/8.4 plus a lowest-dependency job, and a `checks` job doing `composer validate --strict`, `php -l`, and `generate-apis.php --diff`. Because `composer.lock` is gitignored, every job resolves fresh — `ramsey/composer-install`'s default `locked` mode must never be used here.

## Layout

`src/Services/` mirrors DSM APIs; everything else is this package's own plumbing.

```
src/
  Synology.php      entry point
  Api.php           base class for one DSM endpoint
  Service.php       base class for a group of endpoints
  Auth/             Session, SessionStore implementations (InMemory/Callable/Authenticating), Authenticator, Credentials
  Concerns/         shared Api traits
  Contracts/        Connection, SessionStore — the only two interfaces left
  Exceptions/       error-code tables
  Http/             PSR-18 Connection, Endpoint, ErrorMapper
  Message/          Response (concrete, final)
  Registry/         ApiRegistry
  Services/         ← DSM APIs only
    Api/            SYNO.API.Auth, SYNO.API.Info
    Calendar/ Chat/ Contacts/ Core/ MailPlusServer/ Personal/
resources/registry/ dumped *.lib.json (reference data, not autoloaded)
tools/              codegen
```

Two name pairs to keep straight:

- `Sejongtf\Synology\Api` is the **base class**; `Services\Api\{Auth,Info}` are the `SYNO.API.*` **endpoints**.
- `src/Auth/` is **session management**; `Services/Api/Auth.php` is the **DSM login endpoint**. `Auth\Authenticator` uses `Services\Api\Auth`.

## Architecture

**Service** (`src/Service.php`) — a group of DSM APIs. Subclasses declare only a `$apis` map of `'snake_name' => Api::class`; the base resolves and **caches** instances (`$resolved`), exposed via `__get` and `ArrayAccess` (`$chat->channel`, `$chat['channel']`). Two invariants: `$apis` (definitions) is never overwritten by `$resolved` (instances), and `setConnection()` re-injects into cached APIs, dropping any that lack `setConnection()` so they get rebuilt.

**Api** (`src/Api.php`) — one DSM endpoint. Subclasses set `const API_NAME = 'SYNO.X.Y'` and `protected $methods = ['method' => version]`. `raw()` is the single request path: resolves the version (explicit → `$methods` → 1), appends `_sid` when `const AUTH` is true, and **JSON-encodes array param values only** — strings pass through untouched, so never pre-encode *and* pass an array. Everything else (`bool`, `null`) reaches the connection as a PHP value; `Http\Connection::prepare()` is what turns bools into `'true'`/`'false'` and drops nulls. `request()` is literally `raw()?->data()` — README covers when a consumer should reach for `raw()` instead. `__call()` falls back to `$methods`, so any declared method is callable without an explicit PHP method.

> **Do not "fix" string params into JSON.** The official examples quote them (`taskid="51CB…"`, `mode="open"`) and `SYNO.API.Info` advertises `requestFormat: "JSON"`, so blanket encoding looks correct. It isn't needed: an integration run against real hardware confirmed DSM handles raw strings. `ApiRegistry::requiresJsonParams()` exposes the flag if some future API ever does need it.

**Connection** — `Contracts\Connection` (`request`/`getSessionId`/`getEndpoint`). `Http\Connection` is the shipped PSR-18 implementation; a consumer may substitute its own. It returns the **concrete** `Message\Response`, which any implementation can build from a PSR-7 response.

> **`Connection` is not a PSR-18 client** — it sits *above* one. It used to be called `Contracts\Client`/`Http\Client`, which made a consumer hand a PSR-18 `Client` to a `Client`, and made every file that touched both write `use ... as PsrClient`. `Http\Connection` also absorbed the old `Http\Transport`: its PSR-implementation lookups are now static methods (`psrClient()`, `requestFactory()`, `streamFactory()`) on the class they build.

**Entry point** — `Synology` wires endpoint + session store + client and exposes services, accepting both `$syno->mail_account` and `$syno->mailAccount`. README documents the three constructors; what matters here is the asymmetry between them, below.

### Wire format — every rule here was measured against real hardware

Requests go out as **POST with an `application/x-www-form-urlencoded` body**. Everything rides in the body; `SynoToken` is the single exception, and only when the session has one — so the URL is usually bare:

```
POST /webapi/entry.cgi
api=SYNO.Contacts.Contact&version=2&method=list&addressbook_id=3&_sid=…
```

Two rules, both measured, both load-bearing:

- **`SynoToken` must never be in the body.** DSM's CSRF check reads only the query string and the `X-SYNO-TOKEN` header; a token in the body fails the request with **119**, which reads as "SID not found" and sends you hunting in the wrong place. Note also that once a session is created with `enable_syno_token=yes` (our default), *every* request needs the token — `_sid` alone is 119 too.
- **The body must never be empty.** A POST with an empty body is rejected with **101** even when `api`/`version`/`method` sit in the query string — DSM reads the body first and gives up before looking at the query. Keeping the routing keys in the body satisfies this for free; move them to the URL and every parameterless call (`get_timezone`, `Info::query`) breaks. Don't try to compensate by putting a key in both places: when a key appears in query and body, **the body wins** (measured with mismatched `version`).

Why POST at all: `account`/`passwd`/`otp_code` and `_sid` used to ride in the URL of every request and land in NAS access logs and reverse-proxy logs in the clear. It also lifts the URL length ceiling (measured: HTTP 414 at ~8,100 bytes), and stops clients and proxies from auto-retrying `create`-style calls as if they were idempotent GETs.

The cost is that NAS-side and proxy-side logs no longer show which API was called — every line is `POST /webapi/entry.cgi`. PHP-side debugging is unaffected: `TransportException` messages carry `[api::method]` and `RequestException` holds the PSR-7 response.

**The body is always built with a `StreamFactoryInterface`, never by writing into `createRequest()`'s stream.** PSR-17 makes no promise that a new request's body is writable or seekable, so `getBody()->write()` works only by luck of the implementation. `Http\Connection` therefore takes a stream factory as its third constructor argument; `Connection::streamFactory()` resolves it, preferring the request factory when one class implements both (the common case) before falling back to discovery.

### Who attaches what — load-bearing, don't collapse

- **`_sid` is attached by `Api::raw()`, never by `Http\Connection`.** `Api` is the only layer that knows `AUTH`, and `const AUTH = false` APIs (`SYNO.Chat.External` bot tokens, `SYNO.API.Info` pre-login discovery, `SYNO.API.Auth` login) must not receive one. It arrives inside `$params` and rides in the body with everything else — the connection never inspects or relocates it.
- **`SynoToken` is attached by `Http\Connection`**, but only to requests already carrying `_sid` — CSRF pairs with session auth. It is the one key the connection pulls out of the request and puts in the URL, because DSM won't read it from the body.
- **Session expiry (106/107/119) is retried once, only when credentials exist.** `connect()` hands `Http\Connection::onSessionExpired()` a re-login callback; `withSession()`/`withStore()` never set it, so with an injected sid the error surfaces for the consumer to handle. **The callback is passed the `_sid` that was on the failed request** — `reissue()` is the only place that value still exists, and a consumer whose store is shared across processes needs it to tell "my session expired" from "someone else already replaced it" (compare-and-swap; re-logging in on a CAS hit just cuts the other process loose with a 107). `connect()`'s own callback ignores the argument, and PHP lets a zero-arg closure be called with one, so consumer callbacks written before this still work. **The default callback deliberately does a bare `refresh()` with no CAS** — `connect()` defaults to a process-memory store where there is nothing to compare against, and where a store *is* shared, the lock policy belongs to whoever built it. A consumer keeps `connect()` and overrides `connection()->onSessionExpired()`; both READMEs and the `$store` param doc say so, because the last consumer read the omission as "connect() can't do CAS, assemble by hand".
- **`Http\ErrorMapper` picks the exception class from the API name, except 106/107/119**, which always map to `AuthException`. A session timeout during a Calendar call is an auth problem, not a service problem, so `catch (AuthException)` handles refresh anywhere.

### Session and login

`connect()` does not log in — it wraps the consumer's store in `Auth\AuthenticatingStore`, which logs in the first time something asks for a session that isn't there. That keeps a service-container boot from hitting the network.

Two things stop that from recursing, and both are easy to break:

- `Http\Connection::request()` checks `isset($request['_sid'])` **before** calling `sessions->get()`. Hoisting the store lookup above that check makes a bot-token call trigger the lazy login, and makes the login request itself re-enter the store. `SynologyTest::test_a_bot_token_api_never_triggers_the_lazy_login` pins it.
- `Authenticator` is handed the **inner** store, not the decorator.

**`Authenticator` holds two pieces of mutable state, and both exist for 2FA.** `Credentials` is immutable and stays that way; these live on the authenticator because they change with each login.

- **`$deviceId` — carried forward.** `enable_device_token=yes` makes DSM return `did`; each successful login stores it, and every later login sends it as `device_id`. Without this, an account with enforced 2FA can never be re-authenticated automatically: `refresh()` would ask for a password-only login and get 403. `refresh()` also reads the `did` off the **current session before calling `forget()`** — with a consumer-owned store the session may have been written by another process, so that read is the only chance to see it. Moving the `forget()` above it silently reintroduces the whole problem, and no test of the retry path fails. **A consumer can reintroduce it from outside too**, by clearing the store in its own `onSessionExpired` callback before calling `refresh()` — the callback must leave the expiring session in place and let `refresh()` do the clearing. Both READMEs and the two docblocks say so.
- **`$otpCode` — consumed once, on send.** A TOTP code is single-use, so it is cleared **before** the login call, not after a successful one. Clearing it afterwards leaves a rejected code in place: the next login replays it, earns another 404 (*failed to authenticate 2-factor code*), and that 404 masks the one useful signal, 403 (*code required*) — the code a consumer branches on via `AuthException::requiresOtp()`. In a resident worker (Octane and friends) the authenticator outlives the request, so it sticks there permanently. `login(?string $otpCode)` takes a fresh code for the retry; that's why `Credentials::withOtpCode()` no longer exists (it built a new value object the live authenticator had no way to accept).

### Response abstractions

- `src/Message/` holds exactly one class: `Response`, `final`, wrapping a PSR-7 response.
- **`data()` and `error()` return plain arrays, not value objects.** `ResponseData`/`ResponseError` used to wrap them. Measured before deleting: `get()`/`has()`/`jsonSerialize()` had **zero** callers, and all six `toArray()` call sites were `$data?->toArray() ?? []` — wrap, then immediately unwrap. The immutability those classes enforced (`offsetSet` throwing) is something **PHP arrays already give you for free**, since arrays are copy-on-write value types: a caller mutating the returned array cannot reach back into the `Response`. `MessageTest` pins that.
- Before that they were interfaces with exactly one implementation each, which let the test fakes drift from the real envelope-reading code. Both steps were the same mistake at different sizes — don't reintroduce either layer unless a second real implementation shows up.
- **`errorCode(): ?int` exists so the hot paths don't index into the array.** Session-expiry checks and exception-class selection only ever want the code.
- `Response` is what Api code uses: `success()`, `data()`, `hasData()`, `dataRaw()`, `dataIsObject()`, `error()`, `errorCode()`, `throw()`, `isJsonOrPlaintext()`, `header()`, `toPsrResponse()`.
- `Message\Response` decodes **only when `isJsonOrPlaintext()`**, so a huge download is never buffered; use `toPsrResponse()->getBody()` for binary. It decodes with `json_decode($body)` (objects, not assoc) because assoc mode cannot tell `{}` from `[]` — that distinction is what `dataIsObject()` reports. For a non-JSON body `success()` falls back to the HTTP status, which catches endpoints that fail with a bare 404 instead of a JSON envelope.

### Errors

Hierarchy: `Exceptions\RequestException` (holds the PSR response) → `ApiException` → `FileOperationException` → `Services\Calendar\CalendarException`, which overrides the table because the Calendar guide words the same codes differently. `TransportException` sits apart — failures before any response exists. README lists which code table each class carries.

`ErrorMapper` never selects `FileOperationException` itself: the package ships no FileStation service, so today the file-operation table is reachable only through `CalendarException`. It stays a class of its own because that table is shared, not Calendar's — a FileStation service would map straight to it.

The one thing not to "simplify": **`AuthException`'s login table (400–410) collides numerically with the file-operation table, which is why the two cannot be merged.**

`TransportException` is the only one `src/` raises on its own (`Http\Connection::sendPsr()`, when the PSR-18 client fails). **The DSM-error classes are never thrown by the package** — they are built in `Response::throw()` from the factory `ErrorMapper::for()` supplies. So a plain `request()` returns `null` on a DSM error; only a consumer who calls `raw()->throw()` sees an exception. The 106/107/119 retry branches on `errorCode()`, not on a caught exception.

### Conventions

- Method/API names mirror DSM's snake_case (`channel_member`, `get_photo`); a PHP reserved word gets a `_` suffix (a `List` API becomes `List_`).
- Api methods build a `$params` array and end with `return $this->request(__FUNCTION__, $this->methods[__FUNCTION__], $params);`. Booleans go out as the strings `'true'`/`'false'`.
- Binary endpoints (`Personal\Api\Profile\Photo`, `Chat\Api\External::post_file_get`) branch on `isJsonOrPlaintext()` but still go through `raw()` so `_sid` is attached — **never call `$this->connection->request(...)` directly from an Api method.**
- **There is no multipart transport**, so upload methods are deliberately left out of `$methods` even where the registry advertises them (`SYNO.Personal.Profile.Photo::upload`). Codegen reports them as `미선언`; that line is expected, not drift to fix. Adding one back means adding `multipart/form-data` support to `Http\Connection` first.
- **Array param values are JSON-encoded in `Api::raw()`, once.** Never `json_encode()` in an Api method — the flags drift apart and the same value goes out two ways. `Http\Connection::prepare()` keeps a matching branch (same `JSON_UNESCAPED_UNICODE`) only as a net for code that calls a `Connection` directly.
- Every `src/` file declares `strict_types=1`.
- `@method`/`@property` docblocks are what give IDEs the magic-call and magic-property signatures — keep them in sync with `$methods`/`$apis`.
- APIs return a plain `array` (or a raw `Message\Response` for binary). **There is no model/entity layer**; typed entities with date/decimal casting belong in the consumer.
- Comments are in Korean; match that when editing existing files.

### Shared concerns (`src/Concerns/`)

- `NormalizesParams` — `asList()` (scalar-or-array → JSON list, `array_values` so `json_encode` emits `[]` not `{}`), `asBool()` (`'true'`/`'false'`).
- `HandlesBinaryResponse` — `binary()` treats **both** a JSON/plaintext body and a failing HTTP status as errors. `Personal\Api\Profile\Photo::get` deliberately does *not* use it: a JSON body there is a legitimate "no photo" success.

## `resources/registry/*.lib.json`

Dumped DSM API registries — 147 APIs across 10 files, listing `minVersion`/`maxVersion`, `authLevel`, and per-version method names. Outside `src/` because that is the PSR-4 class root; **reference data, not loaded at runtime**.

Use them to look up correct API names, methods and versions. `authLevel: 0` marks APIs that take no session — that is what `const AUTH = false` corresponds to.

> **They are snapshots, not a contract.** Each file was dumped from one NAS at one moment, the files were **not dumped together**, and none of them records the DSM version it came from. DSM adds API versions across releases, so the device you actually talk to may advertise a higher `maxVersion`, a different method list for a given version, or — the one no diff will ever show you — **different parameter semantics for a method whose name and version both survived.** That last case is why `SYNO.Chat.Channel::enter` is pinned to v2 although the registry offers v5.
>
> So: use the dumps to get names, spelling and rough version ranges right, and confirm anything load-bearing against the hardware — `$syno->discover()` at runtime, or the integration suite. When you re-dump, re-dump every file if you can, and say in the commit message which DSM version it came from.
>
> Note what `discover()` does and does not do. It always returns the fetched `ApiRegistry`, but it only *applies* anything when the connection is the shipped `Http\Connection` — the merge sits behind an `instanceof Http\Connection` guard, because `useRegistry()` is not part of `Contracts\Connection`. With a consumer-supplied connection the call is a pure read and applying the result is that implementation's job. And even where it does apply, request time reads only `path()`: **the version on the wire always comes from the class's `$methods`.** `ApiRegistry::negotiate()` exists and is tested but has *no production caller* — wiring it in would quietly move pinned APIs onto whatever version the server advertises, which is exactly the failure the pins exist to prevent.

`SYNO.API.Auth.lib.json` / `SYNO.API.Info.lib.json` cover the protocol itself rather than a service, so they are the one place where a **method disappears in a later version**: `token`, `session`, `synotoken` and `oidc` exist only in v6 and are gone in v7. That is why `Services\Api\Auth` pins to 6 — the doc's recommendation and the registry agree, and moving to `maxVersion` would break `token`.

## Codegen (`tools/`)

```bash
php tools/generate-apis.php          # generate/update
php tools/generate-apis.php --diff   # report only, exit 1 if anything would change
```

`tools/apis.php` is the config: per service, which registry file, where classes go, and `include` of `'all'` or an explicit list. MailPlusServer uses an explicit list because most of its 65 APIs are admin internals (Cluster/Diagnosis/Audit) — add a name when you need one.

Two rules, both load-bearing:

- **It never overwrites an existing Api file.** Hand-written signatures and deliberate version pins must survive. Where a committed class disagrees with the registry it *reports* the difference instead of "fixing" it — the disagreement is usually intentional (`SYNO.Chat.Channel::enter` is pinned to v2 though the registry offers v5, because higher versions change parameter semantics). **A drift line is not evidence the class is wrong**: the other explanation is that the dump is older or newer than the device (see the snapshot warning above). Settle it against hardware, never from the diff alone.
- **It does rewrite each Service's `@property` block and `$apis` map**, built from the classes actually present in that service's `Api/` directory. That keeps registration from drifting and lets hand-added classes survive regeneration.

The registry has API names, versions, methods and `authLevel` — but **not parameter signatures**. Generated classes stop at `$methods` plus `@method` docblocks and are called magically (`$api->method([...])`). Fill in real signatures by editing the file; the generator then leaves it alone.

`GeneratedApiTest` guards the output: everything registered resolves, no two classes claim the same DSM API, registration keys match class names, and `--diff` stays empty.

## Tests

`tests/Fixtures/` exercises the whole stack without a network: `FakeConnection` (records each `request()` call, returns a canned response), `FakeResponse` (a factory for real `Message\Response` objects — `json()`, `error()`, `binary()`, `failedBinary()`), `FakePsrClient` (PSR-18, records requests, can be given a response sequence), `StubApi`. Prefer these over mocks.

`nyholm/psr7` is **dev-only**. Never reference `Nyholm\` from `src/` — `DependencyGuardTest` fails if you do, because it would be missing in a production install.

**Integration tests** (`tests/Integration/`) run against a real NAS and are the only way to settle what the docs leave open. They **skip themselves** unless `SYNOLOGY_URL` is set. To enable: `cp phpunit.xml.dist phpunit.xml` and fill the `SYNOLOGY_*` block — that file is gitignored so credentials stay local. Auth takes either `SYNOLOGY_SID` (inject a session, no login) or `SYNOLOGY_ACCOUNT`/`SYNOLOGY_PASSWORD` (+ `SYNOLOGY_OTP`); `SYNOLOGY_VERIFY_TLS=0` for self-signed certs. `CurlClient` is a ~50-line PSR-18 implementation used only here, so enabling the suite pulls in no dependency.

Everything there is **read-only** — keep it that way, it runs against a live NAS.

> **Judge on observable effect, not on `success`.** DSM returns `success: true` for a filter it failed to parse — you get back the whole unfiltered catalogue, or an empty object. `success() && hasData()` therefore passes no matter what. Assert that the result actually narrowed.
