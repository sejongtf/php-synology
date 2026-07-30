# Changelog

Notable changes per release. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

Versions are `0.x`, where **a minor bump is the breaking one** and a patch carries fixes and
backward-compatible additions — the same axis Composer's `^0.2` constraint uses.

## [Unreleased]

## [0.2.3] — 2026-07-30

### Added

- `Synology::to($url)` returns a `Connector` that assembles the client. `withSession()`,
  `withStore()` and `connect()` now delegate to it and behave exactly as before.
- `Connector::onLogin()` wraps the **cold-start login** — the one `AuthenticatingStore` performs
  when the store comes up empty. It receives the default login closure and may return a session
  without calling it. This is the only cover for a login stampede when a store is shared across
  processes and empties (cache TTL lapse, deploy, cache server restart): the expiry retry never
  fires there, because there was no session to expire. Requires `credentials()`; without them
  nothing would ever call it, so `connect()` throws `InvalidArgumentException` rather than
  leave you believing the login is serialized.
- `Connector::onSessionExpired()` replaces the expiry retry at build time, so a client built
  without credentials can handle 106/107/119 itself. The callback takes
  `(string $staleSid, ?Authenticator $auth)`; the second argument exists because the
  `Authenticator` does not exist until the client is built, and a one-argument closure is still
  accepted.

Neither hook is a lock. The package picks no lock, TTL or wait policy — it only makes the two
seams reachable.

## [0.2.2] — 2026-07-30

### Changed

- `Http\Connection::onSessionExpired()` passes the callback **the sid that was on the request
  that just failed**. With a store shared across processes, comparing it against the current
  one tells you whether another process already refreshed; logging in again there would cut
  that process loose with a 107. Existing zero-argument callbacks keep working — PHP ignores
  extra arguments to userland closures.

### Documented

- A re-authentication callback must not clear the store before calling
  `Authenticator::refresh()`. `refresh()` reads the device token off the expiring session
  first, and an emptied store makes that read come up blank, which breaks re-login on an
  account with enforced 2FA.

## [0.2.1] — 2026-07-29

### Fixed

- The OTP code is consumed when it is **sent**, not when the login succeeds. A rejected code
  used to stay on the `Authenticator` and go out again with the next login, so DSM kept
  answering 404 (*failed to authenticate 2-factor code*), which masked the 403 (*code
  required*) a consumer branches on via `AuthException::requiresOtp()`. In a resident worker
  the authenticator outlives the request, so that state stuck permanently.

## [0.2.0] — 2026-07-29

### Removed

- `Auth\Credentials::withOtpCode()` — it built a new value object that a live `Authenticator`
  had no way to accept. Pass a fresh code to `Authenticator::login($otpCode)` instead.
- `Services\Api\Auth::RECOMMENDED_VERSION` — duplicated the version pin already in `$methods`.

Neither had a caller, but both were public, which is what makes this a minor bump.

### Fixed

- The re-login path dropped the device token, so on an account with enforced 2FA the automatic
  session refresh could never succeed. `Authenticator` now carries the `did` forward and reads
  it off the current session **before** clearing the store.

### Documented

- README is English; the Korean copy lives at [README.ko.md](README.ko.md).

## [0.1.0] — 2026-07-29

First public release. PHP 8.2+, PSR-18/17/7 interfaces only — no HTTP implementation bundled.

[Unreleased]: https://github.com/sejongtf/php-synology/compare/0.2.3...0.x
[0.2.3]: https://github.com/sejongtf/php-synology/compare/0.2.2...0.2.3
[0.2.2]: https://github.com/sejongtf/php-synology/compare/0.2.1...0.2.2
[0.2.1]: https://github.com/sejongtf/php-synology/compare/0.2.0...0.2.1
[0.2.0]: https://github.com/sejongtf/php-synology/compare/0.1.0...0.2.0
[0.1.0]: https://github.com/sejongtf/php-synology/releases/tag/0.1.0
