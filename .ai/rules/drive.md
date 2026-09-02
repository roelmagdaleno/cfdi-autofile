---
paths:
  - 'app/Drive/**'
---

# Drive

## Drive talks REST, not the Google SDK, and needs the full drive scope
Do not install `google/apiclient`. Even v2.19.4 pins `guzzlehttp/guzzle ^7.4.5` and `guzzlehttp/psr7 ^2.6`, while Laravel 13 ships Guzzle 8 / psr7 3. Adding it downgrades the HTTP stack of the whole app. The six Drive v3 calls we need live in `app/Drive/Google/GoogleDriveFiles.php` over the `Http` facade.

The OAuth scope must stay the FULL `https://www.googleapis.com/auth/drive`. The restricted `drive.file` only sees files the app itself created, so it cannot find the user's pre-existing `2026 - Emitidas` folders. Google treats the full scope as sensitive: keep the consent screen in "Testing" mode with the user's own account as a test user.

Uploads are `multipart/related` assembled by hand — Laravel's `attach()` sends `multipart/form-data`, which Drive rejects.

`DriveSync` never throws: by the time it runs the files are already renamed and archived, so failures come back as `DriveOutcome` values. `DriveStatus::Skipped` means no folder could exist for that file (no date, or RFC not a party) and is not retried; `Failed` is assumed transient and is.

## The Drive OAuth callback cannot use the .test domain
Google refuses to register any redirect URI that is not on a public TLD ("must end with a public top-level domain"), so `http://cfdi-autofile.test/drive/callback` cannot be an Authorized redirect URI. `localhost` and `127.0.0.1` are the documented exception (any port, plain HTTP); subdomains of localhost are not.

So `GOOGLE_REDIRECT_URI` points at `http://localhost:8000/drive/callback` and the one-time connect is done over `php artisan serve`. The refresh token lands in the `settings` table, so afterwards the integration works from the `.test` domain as usual.

`ConnectDriveController` compares the request host against the configured redirect host and bounces the user back with `ui.drive.wrong_host` when they differ. Do not remove that check: the OAuth `state` lives in a host-scoped session cookie, so a handshake that starts on `.test` and ends on `localhost` loses the state and dies on the 403 in `DriveCallbackController`.

`tests/Feature/Web/ConnectDriveTest.php` derives the redirect from `url()`/`config('app.url')` on purpose — never hardcode a host there, the suite runs with whatever `APP_URL` the developer has.

## The connected account address is resolved lazily, never during the OAuth exchange
`GoogleDriveAuthenticator::exchange()` returns only the refresh token. It used to also call `about.get` to get the account address, and that was a mistake twice over: the failure was invisible (it returned `''`), and a single bad moment left the address blank forever with no recovery but disconnecting.

`App\Drive\DriveAccount::email()` owns it now: it returns the stored address, or asks Drive once and remembers the answer, or returns `null` when Drive will not say. The address is cosmetic — it only tells the user WHICH account they authorized — so a failure here must never surface as an error or block a connection. The settings screen and the callback both go through it, so a blank address heals on the next visit.

`AppSettings::setDriveAccountEmail('')` deletes the row rather than storing an empty string, so "not known yet" has exactly one representation and `DriveAccount` can tell it apart from a real answer.

Read the account address through `DriveFiles::accountEmail()` only — do not add a second place that calls `about.get`.
