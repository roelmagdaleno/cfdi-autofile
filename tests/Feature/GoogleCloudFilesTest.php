<?php

use App\Cloud\Data\CloudDestination;
use App\Cloud\Data\CloudUpload;
use App\Cloud\Exceptions\CloudNotConnectedException;
use App\Cloud\Exceptions\CloudRequestException;
use App\Cloud\Google\GoogleAccessToken;
use App\Cloud\Google\GoogleCloudFiles;
use App\Support\AppSettings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * The real REST client, with Google faked at the HTTP layer.
 *
 * `FakeCloudFiles` covers how the rest of the app uses this class; these tests
 * cover the wire format, which is the part a fake can never check.
 */
beforeEach(function (): void {
    config()->set('services.google.client_id', 'client-id');
    config()->set('services.google.client_secret', 'client-secret');

    app(AppSettings::class)->setDriveRefreshToken('refresh-token-abc');

    $this->files = fn (): GoogleCloudFiles => new GoogleCloudFiles(new GoogleAccessToken(app(AppSettings::class)));
});

/**
 * A destination whose handle is a Drive folder id, which is all this class
 * knows about the concept.
 */
function destination(string $folderId): CloudDestination
{
    return new CloudDestination($folderId, '2026 - Emitidas');
}

function fakeGoogle(array $responses = []): void
{
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-token-xyz', 'expires_in' => 3599]),
        ...$responses,
    ]);
}

it('trades the refresh token for an access token and authorizes with it', function (): void {
    fakeGoogle(['*/drive/v3/about*' => Http::response(['user' => ['emailAddress' => 'facturas@example.com']])]);

    expect(($this->files)()->accountLabel())->toBe('facturas@example.com');

    Http::assertSent(fn (Request $r): bool => $r->url() === GoogleAccessToken::ENDPOINT
        && $r['grant_type'] === 'refresh_token'
        && $r['refresh_token'] === 'refresh-token-abc');

    Http::assertSent(fn (Request $r): bool => str_contains($r->url(), '/drive/v3/about')
        && $r->hasHeader('Authorization', 'Bearer access-token-xyz'));
});

// A run of twenty invoices must not perform twenty token exchanges.
it('exchanges the token once per instance', function (): void {
    fakeGoogle(['*/drive/v3/about*' => Http::response(['user' => ['emailAddress' => 'a@b.c']])]);

    $files = ($this->files)();
    $files->accountLabel();
    $files->accountLabel();

    Http::assertSentCount(3);
});

it('finds an existing folder without creating one', function (): void {
    fakeGoogle(['*/drive/v3/files?*' => Http::response(['files' => [['id' => 'existing-folder']]])]);

    expect(($this->files)()->ensureFolder('root', '2026 - Emitidas'))->toBe('existing-folder');

    // The query is what lets it see a folder the user made by hand.
    Http::assertSent(fn (Request $r): bool => str_contains($r->url(), 'drive/v3/files')
        && str_contains(urldecode($r->url()), "name = '2026 - Emitidas'")
        && str_contains(urldecode($r->url()), "'root' in parents")
        && str_contains(urldecode($r->url()), 'trashed = false'));

    Http::assertNotSent(fn (Request $r): bool => $r->method() === 'POST' && str_contains($r->url(), 'drive/v3/files'));
});

it('creates the folder when the search comes back empty', function (): void {
    Http::fakeSequence()
        ->push(['access_token' => 'access-token-xyz'])
        ->push(['files' => []])
        ->push(['id' => 'new-folder']);

    expect(($this->files)()->ensureFolder('root', '2027 - Emitidas'))->toBe('new-folder');

    Http::assertSent(fn (Request $r): bool => $r->method() === 'POST'
        && str_contains($r->url(), 'drive/v3/files')
        && $r['mimeType'] === GoogleCloudFiles::FOLDER_MIME_TYPE
        && $r['name'] === '2027 - Emitidas'
        && $r['parents'] === ['root']);
});

// A counterparty called "O'Brien" would otherwise produce an invalid query.
it('escapes quotes in a folder name', function (): void {
    fakeGoogle(['*/drive/v3/files?*' => Http::response(['files' => [['id' => 'f']]])]);

    ($this->files)()->ensureFolder('root', "O'Brien {year}");

    Http::assertSent(fn (Request $r): bool => str_contains(urldecode($r->url()), "name = 'O\\'Brien {year}'"));
});

it('uploads the file as a multipart related body', function (): void {
    $path = cfdiTempDir().'/factura.xml';
    file_put_contents($path, '<cfdi>hola</cfdi>');

    fakeGoogle(['*/upload/drive/v3/files*' => Http::response(['id' => 'file-1', 'webViewLink' => 'https://drive.google.com/x'])]);

    $file = ($this->files)()->put(new CloudUpload($path, 'factura.xml', 'application/xml', destination('folder-1')));

    expect($file->id)->toBe('file-1')->and($file->link)->toBe('https://drive.google.com/x');

    Http::assertSent(function (Request $r): bool {
        $body = $r->body();

        return $r->method() === 'POST'
            && str_contains($r->url(), 'uploadType=multipart')
            && str_contains((string) $r->header('Content-Type')[0], 'multipart/related; boundary=')
            && str_contains($body, '"name":"factura.xml"')
            && str_contains($body, '"parents":["folder-1"]')
            && str_contains($body, '<cfdi>hola</cfdi>');
    });
});

it('replaces a file with a patch that moves it out of its old folder', function (): void {
    $path = cfdiTempDir().'/factura.xml';
    file_put_contents($path, '<cfdi/>');

    Http::fakeSequence()
        ->push(['access_token' => 'access-token-xyz'])
        ->push(['parents' => ['old-folder']])
        ->push(['id' => 'file-1', 'webViewLink' => 'https://drive.google.com/x']);

    ($this->files)()->put(new CloudUpload($path, 'nuevo.xml', 'application/xml', destination('new-folder'), 'file-1'));

    Http::assertSent(fn (Request $r): bool => $r->method() === 'PATCH'
        && str_contains($r->url(), 'files/file-1')
        && str_contains(urldecode($r->url()), 'addParents=new-folder')
        && str_contains(urldecode($r->url()), 'removeParents=old-folder'));
});

// The user deleted the file from Drive by hand. Failing here would leave them
// without their invoice, so the upload starts over instead.
it('uploads again when the file it should replace is gone', function (): void {
    $path = cfdiTempDir().'/factura.xml';
    file_put_contents($path, '<cfdi/>');

    Http::fakeSequence()
        ->push(['access_token' => 'access-token-xyz'])
        ->push(['parents' => ['old-folder']])
        ->push(['error' => ['message' => 'File not found: file-1']], 404)
        ->push(['id' => 'file-2', 'webViewLink' => 'https://drive.google.com/y']);

    $file = ($this->files)()->put(new CloudUpload($path, 'nuevo.xml', 'application/xml', destination('new-folder'), 'file-1'));

    expect($file->id)->toBe('file-2');

    Http::assertSent(fn (Request $r): bool => $r->method() === 'POST'
        && str_contains($r->url(), 'uploadType=multipart'));
});

it('falls back to the canonical url when drive omits the view link', function (): void {
    $path = cfdiTempDir().'/factura.xml';
    file_put_contents($path, '<cfdi/>');

    fakeGoogle(['*/upload/drive/v3/files*' => Http::response(['id' => 'file-9'])]);

    expect(($this->files)()->put(new CloudUpload($path, 'a.xml', 'application/xml', destination('f')))->link)
        ->toBe('https://drive.google.com/file/d/file-9/view');
});

it('turns a drive error into an exception carrying google\'s own message', function (): void {
    fakeGoogle(['*/drive/v3/files?*' => Http::response(['error' => ['message' => 'File not found: nope']], 404)]);

    expect(fn () => ($this->files)()->ensureFolder('nope', '2026 - Emitidas'))
        ->toThrow(CloudRequestException::class, 'File not found: nope');
});

it('reports no connection when there is no refresh token', function (): void {
    app(AppSettings::class)->disconnectDrive();

    expect(fn () => ($this->files)()->accountLabel())->toThrow(CloudNotConnectedException::class);

    Http::assertNothingSent();
});

// Google answers `invalid_grant` when the user revoked access from their
// account settings. Keeping the dead token would show a connected account that
// cannot connect.
it('forgets a token google has stopped accepting', function (): void {
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400),
    ]);

    expect(fn () => ($this->files)()->accountLabel())->toThrow(CloudNotConnectedException::class)
        ->and(app(AppSettings::class)->isDriveConnected())->toBeFalse();
});
