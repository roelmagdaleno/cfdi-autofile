<?php

use App\Cloud\Contracts\CloudOAuth;
use App\Cloud\Data\CloudProvider;
use App\Http\Controllers\ConnectCloudController;
use App\Support\AppSettings;
use Tests\Support\FakeCloudOAuth;

beforeEach(function (): void {
    config()->set('services.google.client_id', 'client-id.apps.googleusercontent.com');
    config()->set('services.google.client_secret', 'client-secret');
    // Derived from the app URL rather than hardcoded: the developer's `.env`
    // points this at the `php artisan serve` host used for the one-time
    // connect, and the tests must not care what either host is.
    config()->set('services.google.redirect', url('/drive/callback'));

    $this->authenticator = new FakeCloudOAuth;
    app()->instance(CloudOAuth::class, $this->authenticator);

    $this->settings = app(AppSettings::class);
});

it('redirects to google with a state it remembers', function (): void {
    $response = $this->get(route('cloud.google_drive.connect'));

    $state = session(ConnectCloudController::STATE_KEY);

    expect($state)->toBeString()->not->toBe('');

    $response->assertRedirect('https://accounts.google.com/o/oauth2/v2/auth?state='.$state);
});

// Without an OAuth client there is nothing to redirect to, and Google would
// answer with an opaque error page.
it('sends the user back when the oauth client is not configured', function (mixed $value): void {
    config()->set('services.google.client_id', $value);

    $this->get(route('cloud.google_drive.connect'))
        ->assertRedirect(route('settings.edit'))
        ->assertSessionHas('cloud_error');
})->with([
    'missing' => [null],
    // The keys ship in `.env.example` with an empty value, which reads as
    // configured to `config()`.
    'empty' => [''],
]);

// The address is not part of the exchange: it is resolved from Drive once the
// token is stored. Nothing pre-seeds it here, so this proves that resolution.
it('stores the refresh token and the account when google returns a code', function (): void {
    $this->withSession([ConnectCloudController::STATE_KEY => 'the-state'])
        ->get(route('cloud.google_drive.callback', ['code' => 'auth-code', 'state' => 'the-state']))
        ->assertRedirect(route('settings.edit'))
        ->assertSessionHas('cloud_status');

    expect($this->authenticator->exchanged)->toBe(['auth-code'])
        ->and($this->settings->credential(CloudProvider::GoogleDrive))->toBe('refresh-token-abc')
        ->and($this->settings->accountLabel(CloudProvider::GoogleDrive))->toBe('facturas@example.com')
        ->and($this->settings->isCloudConnected(CloudProvider::GoogleDrive))->toBeTrue();
});

// A refresh token is a long-lived password for the user's whole Drive.
it('never stores the refresh token in the clear', function (): void {
    $this->withSession([ConnectCloudController::STATE_KEY => 'the-state'])
        ->get(route('cloud.google_drive.callback', ['code' => 'auth-code', 'state' => 'the-state']));

    $this->assertDatabaseMissing('settings', [
        'key' => 'cloud_google_drive_'.AppSettings::SUFFIX_CREDENTIAL,
        'value' => 'refresh-token-abc',
    ]);

    expect($this->settings->credential(CloudProvider::GoogleDrive))->toBe('refresh-token-abc');
});

// Without the state check anyone could hand the user a crafted callback URL and
// connect an account of their choosing.
it('refuses a callback whose state does not match the session', function (string $session, array $query): void {
    $this->withSession([ConnectCloudController::STATE_KEY => $session])
        ->get(route('cloud.google_drive.callback', $query))
        ->assertForbidden();

    expect($this->settings->isCloudConnected(CloudProvider::GoogleDrive))->toBeFalse();
})->with([
    'different state' => ['the-state', ['code' => 'auth-code', 'state' => 'other']],
    'no state in the query' => ['the-state', ['code' => 'auth-code']],
    'no state in the session' => ['', ['code' => 'auth-code', 'state' => 'the-state']],
]);

it('sends the user back when they decline the consent screen', function (): void {
    $this->withSession([ConnectCloudController::STATE_KEY => 'the-state'])
        ->get(route('cloud.google_drive.callback', ['error' => 'access_denied', 'state' => 'the-state']))
        ->assertRedirect(route('settings.edit'))
        ->assertSessionHas('cloud_error');

    expect($this->settings->isCloudConnected(CloudProvider::GoogleDrive))->toBeFalse();
});

it('reports a failed exchange instead of throwing', function (): void {
    app()->instance(CloudOAuth::class, new FakeCloudOAuth(fails: true));

    $this->withSession([ConnectCloudController::STATE_KEY => 'the-state'])
        ->get(route('cloud.google_drive.callback', ['code' => 'auth-code', 'state' => 'the-state']))
        ->assertRedirect(route('settings.edit'))
        ->assertSessionHas('cloud_error');

    expect($this->settings->isCloudConnected(CloudProvider::GoogleDrive))->toBeFalse();
});

// The state is single-use: replaying the same callback must not work.
it('consumes the state so a callback cannot be replayed', function (): void {
    $this->withSession([ConnectCloudController::STATE_KEY => 'the-state'])
        ->get(route('cloud.google_drive.callback', ['code' => 'auth-code', 'state' => 'the-state']))
        ->assertRedirect(route('settings.edit'));

    $this->get(route('cloud.google_drive.callback', ['code' => 'auth-code', 'state' => 'the-state']))
        ->assertForbidden();
});

// Following the redirect renders the settings screen with the callout, which is
// the only thing that catches a mistyped Flux component in that branch.
it('renders the settings screen with the outcome after coming back', function (): void {
    $this->withSession([ConnectCloudController::STATE_KEY => 'the-state'])
        ->get(route('cloud.google_drive.callback', ['code' => 'auth-code', 'state' => 'the-state']));

    $this->followingRedirects()
        ->withSession([ConnectCloudController::STATE_KEY => 'other'])
        ->get(route('settings.edit'))
        ->assertOk()
        ->assertSee(__('ui.cloud.heading'))
        ->assertSee('facturas@example.com');
});

it('renders the error callout when the connection failed', function (): void {
    app()->instance(CloudOAuth::class, new FakeCloudOAuth(fails: true));

    $this->withSession([ConnectCloudController::STATE_KEY => 'the-state'])
        ->followingRedirects()
        ->get(route('cloud.google_drive.callback', ['code' => 'auth-code', 'state' => 'the-state']))
        ->assertOk()
        ->assertSee('invalid_grant');
});

// Google rejects a `.test` redirect URI, so the callback has to land on
// localhost. The state lives in a host-scoped session cookie, so a handshake
// that starts on another host loses it -- the user is told instead of getting a
// 403 they cannot interpret.
it('refuses to start the handshake from a host the callback will not return to', function (): void {
    config()->set('services.google.redirect', 'http://localhost:8000/drive/callback');

    $this->get(route('cloud.google_drive.connect'))
        ->assertRedirect(route('settings.edit'))
        ->assertSessionHas('cloud_error', fn (string $message): bool => str_contains($message, 'http://localhost:8000/settings'));

    // Nothing was started, so there is no dangling state either.
    expect(session(ConnectCloudController::STATE_KEY))->toBeNull();
});

it('starts the handshake when the host matches the callback', function (): void {
    $this->get(route('cloud.google_drive.connect'))
        ->assertRedirect('https://accounts.google.com/o/oauth2/v2/auth?state='.session(ConnectCloudController::STATE_KEY));
});

// `Request::getHttpHost()` omits the scheme's default port, so a redirect URI
// written with `:80` must still count as the same host.
it('ignores the default port when comparing hosts', function (): void {
    $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
    config()->set('services.google.redirect', "http://{$host}:80/drive/callback");

    $this->get(route('cloud.google_drive.connect'))
        ->assertRedirect('https://accounts.google.com/o/oauth2/v2/auth?state='.session(ConnectCloudController::STATE_KEY));
});
