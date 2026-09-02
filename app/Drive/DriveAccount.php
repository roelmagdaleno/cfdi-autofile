<?php

namespace App\Drive;

use App\Drive\Contracts\DriveFiles;
use App\Drive\Exceptions\DriveException;
use App\Support\AppSettings;

/**
 * Which Google account is connected.
 *
 * The address is only used to show the user WHICH account they authorized, so
 * it is worth nothing if getting it can break something. It is asked for
 * lazily and remembered the first time it succeeds: an install that connected
 * while Drive was having a bad minute heals itself on the next visit to the
 * settings screen, instead of showing "connected as" followed by nothing
 * forever.
 */
final class DriveAccount
{
    public function __construct(
        private readonly AppSettings $settings,
        private readonly DriveFiles $files,
    ) {}

    public function email(): ?string
    {
        $stored = $this->settings->driveAccountEmail();

        if ($stored !== null) {
            return $stored;
        }

        // Nothing to ask about, and asking would throw.
        if (! $this->settings->isDriveConnected()) {
            return null;
        }

        try {
            $email = $this->files->accountEmail();
        } catch (DriveException) {
            // Cosmetic: the integration works fine without knowing the address,
            // so this must never surface as an error.
            return null;
        }

        $this->settings->setDriveAccountEmail($email);

        return $this->settings->driveAccountEmail();
    }
}
