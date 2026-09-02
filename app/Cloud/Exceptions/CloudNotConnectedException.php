<?php

namespace App\Cloud\Exceptions;

use App\Cloud\Data\CloudProvider;

/**
 * There is no usable refresh token: the user never authorized the app, they
 * revoked it, or `APP_KEY` was rotated and the stored token can no longer be
 * decrypted.
 *
 * All three lead to the same place -- reconnect the account in settings -- so
 * they are deliberately not told apart.
 */
final class CloudNotConnectedException extends CloudException
{
    public static function make(CloudProvider $provider): self
    {
        return new self((string) __('cfdi.cloud.not_connected', ['provider' => $provider->label()]));
    }
}
