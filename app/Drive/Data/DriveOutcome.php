<?php

namespace App\Drive\Data;

/**
 * The per-file result of a sync, ready to be written to a `run_files` row.
 *
 * A `reason` is carried for everything that is not an upload, because "it did
 * not go to Drive" is useless to the user without the why.
 */
final readonly class DriveOutcome
{
    private function __construct(
        public DriveStatus $status,
        public ?string $link,
        public ?string $reason,
    ) {}

    public static function uploaded(string $link): self
    {
        return new self(DriveStatus::Uploaded, $link, null);
    }

    public static function skipped(string $reason): self
    {
        return new self(DriveStatus::Skipped, null, $reason);
    }

    public static function failed(string $reason): self
    {
        return new self(DriveStatus::Failed, null, $reason);
    }
}
