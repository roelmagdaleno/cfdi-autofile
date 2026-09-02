<?php

namespace Tests\Support;

use App\Cloud\Contracts\CloudOAuth;
use App\Cloud\Data\CloudCredentials;
use App\Cloud\Exceptions\CloudRequestException;

/**
 * Stands in for the OAuth handshake so the connect/callback controllers can be
 * driven end to end without faking HTTP.
 */
final class FakeCloudOAuth implements CloudOAuth
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

    public function exchange(string $code): CloudCredentials
    {
        $this->exchanged[] = $code;

        if ($this->fails) {
            throw CloudRequestException::make('exchange', 400, 'invalid_grant');
        }

        return new CloudCredentials($this->refreshToken);
    }
}
