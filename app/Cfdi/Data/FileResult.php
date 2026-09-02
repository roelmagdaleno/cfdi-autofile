<?php

namespace App\Cfdi\Data;

/**
 * Una fila del resumen.
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
            $action->value,
            $placed,
        );
    }
}
