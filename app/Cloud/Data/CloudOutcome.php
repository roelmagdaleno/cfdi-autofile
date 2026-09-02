<?php

namespace App\Cloud\Data;

/**
 * The per-file result of a sync, ready to be written to a `run_files` row.
 *
 * A `reason` is carried for everything that is not an upload, because "it did
 * not go to Drive" is useless to the user without the why.
 */
final readonly class CloudOutcome
{
    private function __construct(
        public CloudStatus $status,
        public ?string $link,
        public ?string $reason,
    ) {}

    public static function uploaded(string $link): self
    {
        return new self(CloudStatus::Uploaded, $link, null);
    }

    public static function skipped(string $reason): self
    {
        return new self(CloudStatus::Skipped, null, $reason);
    }

    public static function failed(string $reason): self
    {
        return new self(CloudStatus::Failed, null, $reason);
    }
}
