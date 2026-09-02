<?php

namespace App\Drive\Contracts;

use App\Drive\Data\DriveCredentials;
use App\Drive\Exceptions\DriveException;

/**
 * The OAuth half of the integration, kept apart from `DriveFiles` so the
 * connect/callback controllers can be tested without faking HTTP.
 */
interface DriveAuthenticator
{
    /**
     * Where to send the user to authorize the app. `$state` comes back
     * unchanged on the callback and is what makes the redirect tamper-evident.
     */
    public function authUrl(string $state): string;

    /**
     * @throws DriveException
     */
    public function exchange(string $code): DriveCredentials;
}
