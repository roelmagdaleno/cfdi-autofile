<?php

namespace App\Cloud\Data;

/**
 * Where one file is going.
 *
 * `handle` is opaque on purpose -- a Drive folder id, an S3 key prefix, a
 * Dropbox path -- and only the provider that produced it ever reads it. `label`
 * is the half the user sees: the settings screen and the toasts show it, so it
 * must stay readable ("2026 - Recibidas"), never an id.
 */
final readonly class CloudDestination
{
    public function __construct(
        public string $handle,
        public string $label,
    ) {}
}
