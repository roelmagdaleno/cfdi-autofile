<?php

namespace App\Cloud;

use App\Cloud\Contracts\CloudFiles;
use App\Cloud\Exceptions\CloudException;
use App\Support\AppSettings;

/**
 * Which account the active provider is connected to.
 *
 * The label is only used to show the user WHICH account they authorized, so it
 * is worth nothing if getting it can break something. It is asked for lazily
 * and remembered the first time it succeeds: an install that connected while
 * the provider was having a bad minute heals itself on the next visit to the
 * settings screen, instead of showing "connected as" followed by nothing
 * forever.
 */
final class CloudAccount
{
    public function __construct(
        private readonly AppSettings $settings,
        private readonly CloudFiles $files,
    ) {}

    public function label(): ?string
    {
        $provider = $this->settings->cloudProvider();
        $stored = $this->settings->accountLabel($provider);

        if ($stored !== null) {
            return $stored;
        }

        // Nothing to ask about, and asking would throw.
        if (! $this->settings->isCloudConnected($provider)) {
            return null;
        }

        try {
            $label = $this->files->accountLabel();
        } catch (CloudException) {
            // Cosmetic: the integration works fine without knowing the account,
            // so this must never surface as an error.
            return null;
        }

        $this->settings->setAccountLabel($provider, $label);

        return $this->settings->accountLabel($provider);
    }
}
