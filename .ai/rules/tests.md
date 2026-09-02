---
paths:
  - 'tests/**'
---

# Tests

## The `documents` disk is faked globally in tests/Pest.php
`DocumentArchiver` copies every processed CFDI to the `documents` disk (storage/app/cfdis). Any test that processes a CFDI triggers it, so `tests/Pest.php` does `Storage::fake(Document::DISK)` in a global `beforeEach` for all of `Feature`.

Without that fake the tests write to the real storage, and since `RefreshDatabase` does reset the database but the disk is not reset, folio-based deduplication cannot find the previous row and the archive fills up with ` (2)`, ` (3)`, … duplicates. Do not remove that fake or replace it with a per-file one.

## Unit tests boot the app too
The domain layer resolves its messages through `__()`, which needs the container and the translator, so `tests/Pest.php` also extends `TestCase` for `Unit` — without `RefreshDatabase`, because those tests do not touch the database.

## Tests run under the `en` locale
`phpunit.xml` pins `APP_LOCALE=en` so the suite is deterministic no matter what the developer's `.env` says. Assert against the English strings. The one place the Spanish locale is exercised on purpose is `tests/Unit/FileNameBuilderTest.php`, which checks that `App::setLocale('es')` still produces the original ` (nota de credito)` file-name suffix.
