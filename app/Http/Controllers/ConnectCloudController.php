<?php

namespace App\Http\Controllers;

use App\Cloud\Contracts\CloudOAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Starts the authorization of the active provider.
 *
 * The `state` is stored in the session and compared on the way back, which is
 * what makes the callback tamper-evident: without it, anyone could hand the user
 * a crafted callback URL and connect an account of their choosing.
 */
final class ConnectCloudController extends Controller
{
    public const string STATE_KEY = 'cloud_oauth_state';

    public function __invoke(Request $request, CloudOAuth $authenticator): RedirectResponse
    {
        // Without an OAuth client there is nothing to redirect to, and Google
        // would answer with an opaque error page. `blank()` rather than a null
        // check: the keys ship in `.env.example` with an empty value, which
        // reads as configured to `config()`.
        if (blank(config('services.google.client_id')) || blank(config('services.google.client_secret'))) {
            return redirect()
                ->route('settings.edit')
                ->with('cloud_error', (string) __('ui.cloud.google_drive.missing_credentials'));
        }

        // Google rejects any redirect URI that is not on a public TLD, so a
        // `.test` domain cannot be registered and the callback has to come back
        // to `localhost`. The `state` lives in the session and the session
        // cookie is scoped to one host, so a handshake that STARTS here and
        // ENDS on localhost loses it and fails the state check. Saying so beats
        // a 403 the user cannot interpret.
        $expectedHost = $this->hostOf((string) config('services.google.redirect'));

        if ($expectedHost !== null && $expectedHost !== $request->getHttpHost()) {
            return redirect()
                ->route('settings.edit')
                ->with('cloud_error', (string) __('ui.cloud.google_drive.wrong_host', [
                    'url' => 'http://'.$expectedHost.route('settings.edit', absolute: false),
                    'host' => $expectedHost,
                ]));
        }

        $state = Str::random(40);

        $request->session()->put(self::STATE_KEY, $state);

        return redirect()->away($authenticator->authUrl($state));
    }

    /**
     * Host and port of a URL, in the same shape `Request::getHttpHost()`
     * returns: the port is only present when it is not the scheme's default.
     */
    private function hostOf(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        $port = parse_url($url, PHP_URL_PORT);
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $default = $scheme === 'https' ? 443 : 80;

        return is_int($port) && $port !== $default ? "{$host}:{$port}" : $host;
    }
}
