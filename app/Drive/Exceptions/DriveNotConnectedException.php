<?php

namespace App\Drive\Exceptions;

/**
 * There is no usable refresh token: the user never authorized the app, they
 * revoked it, or `APP_KEY` was rotated and the stored token can no longer be
 * decrypted.
 *
 * All three lead to the same place -- reconnect the account in settings -- so
 * they are deliberately not told apart.
 */
final class DriveNotConnectedException extends DriveException
{
    public static function make(): self
    {
        return new self((string) __('cfdi.drive.not_connected'));
    }
}
