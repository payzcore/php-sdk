# payzcore/payzcore-php v1.0.3

**Upgrade recommended for everyone on 1.0.2.** In 1.0.2, `$payzcore->payments->create()` read a missing `existing` key on every new payment. Frameworks that turn warnings into exceptions (Laravel, Symfony) threw after the payment had been created.

```bash
composer require payzcore/payzcore-php:^1.0.3
```

## Fixed
- `create()` returns `existing => false` for new payments (no warning).
- `projects->list()` returns `apiKeyPrefix`.
- `Webhook::constructEvent()` accepts payloads with `network` / `address` = `null`.
- No automatic retry of `POST` without `externalOrderId` (prevents duplicate payments).
- PHP 8.5: removed deprecated `curl_close()`.

## Added
- `list(['createdAfter' => ..., 'updatedAfter' => ...])` and `$result['pagination']`.
- `updatedAt` on list items, `paidAt` on `get()`.
- `composer test` (dependency-free regression tests).

Full changelog: [CHANGELOG.md](CHANGELOG.md) · Docs: https://docs.payzcore.com/sdks/php
