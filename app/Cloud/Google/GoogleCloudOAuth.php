<?php

namespace App\Cloud\Google;

use App\Cloud\Contracts\CloudOAuth;
use App\Cloud\Data\CloudCredentials;
use App\Cloud\Exceptions\CloudRequestException;
use Illuminate\Support\Facades\Http;

/**
 * The OAuth authorization-code dance, over the `Http` facade.
 *
 * The scope is the FULL `drive` scope, not the restricted `drive.file`, and
 * that is a requirement rather than a preference: `drive.file` only grants
 * access to files the application itself created, so it cannot even see the
 * `2026 - Emitidas` folder the user made by hand years ago. Google treats the
 * full scope as sensitive, which for a local single-user app means leaving the
 * consent screen in "Testing" mode with the user's own account as a test user.
 */
final class GoogleCloudOAuth implements CloudOAuth
{
    public const string SCOPE = 'https://www.googleapis.com/auth/drive';

    public const string AUTH_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';

    public function authUrl(string $state): string
    {
        return self::AUTH_ENDPOINT.'?'.http_build_query([
            'client_id' => (string) config('services.google.client_id'),
            'redirect_uri' => (string) config('services.google.redirect'),
            'response_type' => 'code',
            'scope' => self::SCOPE,
            // Without `offline` Google returns an access token and no refresh
            // token, and the integration would stop working within the hour.
            'access_type' => 'offline',
            // Google only re-issues a refresh token when consent is granted
            // again. Without this, re-authorizing an already-authorized account
            // silently yields no refresh token at all.
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);
    }

    public function exchange(string $code): CloudCredentials
    {
        $response = Http::asForm()->post(GoogleAccessToken::ENDPOINT, [
            'client_id' => (string) config('services.google.client_id'),
            'client_secret' => (string) config('services.google.client_secret'),
            'redirect_uri' => (string) config('services.google.redirect'),
            'grant_type' => 'authorization_code',
            'code' => $code,
        ]);

        if ($response->failed()) {
            throw CloudRequestException::make(
                'exchange',
                $response->status(),
                (string) $response->json('error_description', ''),
            );
        }

        $refreshToken = $response->json('refresh_token');

        if (! is_string($refreshToken) || $refreshToken === '') {
            throw CloudRequestException::make('exchange', $response->status(), (string) __('cfdi.drive.no_refresh_token'));
        }

        // The account's address is deliberately NOT read here: see
        // `CloudAccount`, which resolves it lazily and can retry.
        return new CloudCredentials($refreshToken);
    }
}
