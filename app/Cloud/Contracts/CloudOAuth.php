<?php

namespace App\Cloud\Contracts;

use App\Cloud\Data\CloudCredentials;
use App\Cloud\Exceptions\CloudException;

/**
 * The OAuth half of the integration, kept apart from `CloudFiles` so the
 * connect/callback controllers can be tested without faking HTTP.
 */
interface CloudOAuth
{
    /**
     * Where to send the user to authorize the app. `$state` comes back
     * unchanged on the callback and is what makes the redirect tamper-evident.
     */
    public function authUrl(string $state): string;

    /**
     * @throws CloudException
     */
    public function exchange(string $code): CloudCredentials;
}
