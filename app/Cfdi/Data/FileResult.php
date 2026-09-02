<?php

namespace App\Cfdi\Data;

/**
 * One row of the summary.
 */
final class FileResult
{
    public function __construct(
        public readonly FileStatus $status,
        public readonly string $original,
        public readonly ?string $newName,
        public readonly string $note,
        public readonly ?PlacedFile $placed = null,
    ) {}

    public static function rejected(string $original, string $reason): self
    {
        return new self(FileStatus::Rejected, $original, null, $reason);
    }

    public static function placed(Confidence $confidence, string $original, string $newName, FileAction $action, PlacedFile $placed): self
    {
        return new self(
            $confidence === Confidence::Low ? FileStatus::Review : FileStatus::Ok,
            $original,
            $newName,
            // The note is rendered copy, not a key: it is stored as-is in
            // `run_files.note` and read back as a historical record.
            $action->label(),
            $placed,
        );
    }
}
