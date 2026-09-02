---
paths:
  - 'app/Cloud/**'
---

# Cloud

## Drive talks REST, not the Google SDK, and needs the full drive scope
Do not install `google/apiclient`. Even v2.19.4 pins `guzzlehttp/guzzle ^7.4.5` and `guzzlehttp/psr7 ^2.6`, while Laravel 13 ships Guzzle 8 / psr7 3. Adding it downgrades the HTTP stack of the whole app. The six Drive v3 calls we need live in `app/Cloud/Google/GoogleCloudFiles.php` over the `Http` facade.

The OAuth scope must stay the FULL `https://www.googleapis.com/auth/drive`. The restricted `drive.file` only sees files the app itself created, so it cannot find the user's pre-existing `2026 - Emitidas` folders. Google treats the full scope as sensitive: keep the consent screen in "Testing" mode with the user's own account as a test user.

Uploads are `multipart/related` assembled by hand — Laravel's `attach()` sends `multipart/form-data`, which Drive rejects.

`CloudSync` never throws: by the time it runs the files are already renamed and archived, so failures come back as `CloudOutcome` values. `CloudStatus::Skipped` means no folder could exist for that file (no date, or RFC not a party) and is not retried; `Failed` is assumed transient and is.

## The Drive OAuth callback cannot use the .test domain
Google refuses to register any redirect URI that is not on a public TLD ("must end with a public top-level domain"), so `http://cfdi-autofile.test/drive/callback` cannot be an Authorized redirect URI. `localhost` and `127.0.0.1` are the documented exception (any port, plain HTTP); subdomains of localhost are not.

So `GOOGLE_REDIRECT_URI` points at `http://localhost:8000/drive/callback` and the one-time connect is done over `php artisan serve`. The refresh token lands in the `settings` table, so afterwards the integration works from the `.test` domain as usual.

`ConnectCloudController` compares the request host against the configured redirect host and bounces the user back with `ui.cloud.google_drive.wrong_host` when they differ. Do not remove that check: the OAuth `state` lives in a host-scoped session cookie, so a handshake that starts on `.test` and ends on `localhost` loses the state and dies on the 403 in `CloudCallbackController`.

`tests/Feature/Web/ConnectCloudTest.php` derives the redirect from `url()`/`config('app.url')` on purpose — never hardcode a host there, the suite runs with whatever `APP_URL` the developer has.

## The connected account is resolved lazily, never during the OAuth exchange
`GoogleCloudOAuth::exchange()` returns only the refresh token. It used to also call `about.get` to get the account address, and that was a mistake twice over: the failure was invisible (it returned `''`), and a single bad moment left the address blank forever with no recovery but disconnecting.

`App\Cloud\CloudAccount::label()` owns it now: it returns the stored address, or asks Drive once and remembers the answer, or returns `null` when Drive will not say. The address is cosmetic — it only tells the user WHICH account they authorized — so a failure here must never surface as an error or block a connection. The settings screen and the callback both go through it, so a blank address heals on the next visit.

`AppSettings::setAccountLabel($provider, '')` deletes the row rather than storing an empty string, so "not known yet" has exactly one representation and `CloudAccount` can tell it apart from a real answer.

Read the account through `CloudFiles::accountLabel()` only — do not add a second place that calls `about.get`.

## The shared contract may not carry the shape of one provider
`CloudFiles` is `put()` + `accountLabel()`, and nothing else. Anything that is true of Google and not of the next provider stays behind Google's own interfaces:

- `ensureFolder()` lives on `App\Cloud\Google\Contracts\GoogleFolders`. "Make sure the folder named X exists under Y and give me its id" has no meaning where the prefix of a key is not an object that can exist, so putting it in the shared contract would force an S3 provider to implement a lie.
- The generic layer asks `CloudDestinations` for a `CloudDestination` instead: an opaque `handle` only the provider that made it reads, plus the `label` the user sees. Drive resolves folder ids over the network; a path-based provider builds a string and never leaves the machine.
- `upload()` and `replace()` are one `put()` carrying an optional `remoteId`. Whether an overwrite is a PATCH, a `mode=overwrite`, or simply writing the same key again is the provider's business — and so is the "that id no longer resolves, start over" recovery, which is a Drive 404 semantic and lives in `GoogleCloudFiles::put()`.

`CloudProvider` is the catalog: it names the class behind every seam, and the container asks it rather than naming Google. Adding a provider is a case here plus its classes and its display name in both locales — the `match` arms have no `default`, so PHP refuses to run with an unwired case, and `TranslationsTest` refuses to pass without the copy.

Every provider so far uses OAuth, so `oauthClass()` returns a plain `string`. The day one connects with pasted keys instead, make it `?string`: the `match` will not compile until that case decides, and that is the moment the connect and callback routes get their `usesOAuth()` guard.

The OAuth routes keep the literal `drive/connect` and `drive/callback` paths. The callback URI is a contract with Google's console, not a naming decision; a second provider brings its own pair of paths.

## `document_cloud_files` is the identity, `run_files.cloud_*` is the snapshot
One row per (document, provider), unique by that pair. `remote_id` is what makes reprocessing the same folio REPLACE the remote copy instead of adding a second one, and `CloudSync` writes it with `updateOrCreate` on exactly the unique key, so idempotence is the database's promise. Keeping it in its own table is what lets a document live at two providers at once, and what makes switching platforms keep the ids of the old one rather than overwrite them.

`run_files` keeps four flattened `cloud_*` columns instead of joining through that table, and the provider is denormalized onto the row on purpose: a run has to keep saying "this file was skipped that day because it had no date, and it was Google Drive that skipped it" forever, even after the document was uploaded successfully or moved elsewhere.

`documents.cloud_files` is eager-loaded wherever more than one document is rendered or synced (`BrowseDocuments`, `CloudSync`), and `Document::cloudFileFor()` reads the loaded relation rather than querying. `DocumentsTest` counts the queries; do not make it read from the model one row at a time.
