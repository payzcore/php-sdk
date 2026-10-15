# Changelog

## 1.0.3 — 2026-10-15

### Fixed
- `payments->create()` read a missing `existing` key for every **new** payment ("Undefined array key" warning; under Laravel/Symfony this became an exception even though the payment was created). `existing` is now `false` when absent.
- `projects->list()` read a missing `api_key` key. Items now have `apiKeyPrefix` (full keys are never listed).
- `Webhook::parseWebhook()` / `constructEvent()` rejected valid payloads whose `network` / `address` are `null` (payment cancelled before a network was selected). Only `event`, `payment_id`, `external_ref`, `expected_amount`, `paid_amount`, `status` and `timestamp` are required now; `network`, `token`, `address` may be `null`. `token` no longer defaults to `'USDT'`.
- `POST` requests are retried only when they carry `externalOrderId`, so a retry after a lost response can no longer create a duplicate payment.
- Removed `curl_close()` (no-op since PHP 8.0, deprecated in PHP 8.5).
- User-Agent reports the real version (was `1.0.0`).

### Added
- `payments->list()` returns `pagination` (`total`, `limit`, `offset`, `hasMore`) and accepts `createdAfter` / `updatedAfter`.
- List items include `updatedAt`; `payments->get()` includes `paidAt`.
- Regression tests without dependencies: `php tests/run.php` (or `composer test`).

### Changed
- `IdempotencyException` is thrown for every HTTP 409 and its default message is now `Conflict`.
- Documented that `RateLimitException::getRetryAfter()` is an absolute Unix timestamp, not a number of seconds.

## 1.0.2 — 2026-03-01

- Webhook signature now covers `timestamp + "." + body`; `timestamp` is required for verification.
