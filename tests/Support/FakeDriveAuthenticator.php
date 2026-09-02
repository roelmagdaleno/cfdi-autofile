<?php

namespace Tests\Support;

use App\Drive\Contracts\DriveAuthenticator;
use App\Drive\Data\DriveCredentials;
use App\Drive\Exceptions\DriveRequestException;

/**
 * Stands in for the OAuth handshake so the connect/callback controllers can be
 * driven end to end without faking HTTP.
 */
final class FakeDriveAuthenticator implements DriveAuthenticator
{
    /** @var list<string> */
    public array $exchanged = [];

    public function __construct(
        public string $refreshToken = 'refresh-token-abc',
        public bool $fails = false,
    ) {}

    public function authUrl(string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?state='.$state;
    }

    public function exchange(string $code): DriveCredentials
    {
        $this->exchanged[] = $code;

        if ($this->fails) {
            throw DriveRequestException::make('exchange', 400, 'invalid_grant');
        }

        return new DriveCredentials($this->refreshToken);
    }
}
