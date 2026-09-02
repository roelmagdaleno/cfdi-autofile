<?php

namespace App\Cloud\Google;

use App\Cloud\Exceptions\CloudNotConnectedException;
use App\Cloud\Exceptions\CloudRequestException;
use App\Support\AppSettings;
use Illuminate\Support\Facades\Http;

/**
 * Trades the stored refresh token for a short-lived access token.
 *
 * The access token is deliberately never persisted: it lives about an hour,
 * whereas a run lasts seconds. It IS memoized for the lifetime of this object,
 * so a run of twenty invoices performs one token exchange instead of twenty.
 */
final class GoogleAccessToken
{
    public const string ENDPOINT = 'https://oauth2.googleapis.com/token';

    private ?string $token = null;

    public function __construct(private readonly AppSettings $settings) {}

    /**
     * @throws CloudNotConnectedException when the user never authorized the app,
     *                                    revoked it, or `APP_KEY` was rotated
     * @throws CloudRequestException
     */
    public function value(): string
    {
        if ($this->token !== null) {
            return $this->token;
        }

        $refreshToken = $this->settings->driveRefreshToken();

        if ($refreshToken === null) {
            throw CloudNotConnectedException::make();
        }

        $response = Http::asForm()->post(self::ENDPOINT, [
            'client_id' => (string) config('services.google.client_id'),
            'client_secret' => (string) config('services.google.client_secret'),
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        // Google answers `invalid_grant` when the user revoked access from their
        // account settings. The token we hold is dead, so it is forgotten here:
        // leaving it would show a connected account that cannot connect.
        if ($response->status() === 400 || $response->status() === 401) {
            $this->settings->disconnectDrive();

            throw CloudNotConnectedException::make();
        }

        if ($response->failed()) {
            throw CloudRequestException::make('token', $response->status(), (string) $response->json('error_description', ''));
        }

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw CloudNotConnectedException::make();
        }

        return $this->token = $token;
    }
}
