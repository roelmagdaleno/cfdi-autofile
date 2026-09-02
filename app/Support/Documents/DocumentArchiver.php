<?php

namespace App\Support\Documents;

use App\Cfdi\Data\FileResult;
use App\Cfdi\Data\PlacedFile;
use App\Cfdi\Data\ProcessResult;
use App\Cfdi\Support\PathExpander;
use App\Models\Counterparty;
use App\Models\Document;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Indexes into `documents` whatever a run has just copied or moved.
 *
 * Two modes, and the difference is deliberate:
 *
 * - **Web** (`copyIntoArchive: true`). The originals are temporary uploads that
 *   `cfdi:clean` deletes, so the archived copy is the only one that will remain.
 *   It is stored on the `documents` disk.
 * - **Command** (`copyIntoArchive: false`). The file already lives where the
 *   user asked with `--output`; copying it again would duplicate every byte for
 *   nothing. Only the absolute path is indexed, and `disk` stays `null`.
 */
final class DocumentArchiver
{
    /** Archive sub-directory used when the CFDI carries no counterparty RFC. */
    private const string NO_RFC = 'NO-RFC';

    /** Archive sub-directory used when the CFDI carries no issue date. */
    private const string NO_DATE = 'no-date';

    /**
     * Indexes the placed rows of the result.
     *
     * @return array<int, Document> keyed by row index in `$result->rows`
     */
    public function archive(ProcessResult $result, bool $copyIntoArchive): array
    {
        $documents = [];

        foreach ($result->rows as $index => $row) {
            $document = $this->archiveRow($row, $copyIntoArchive);

            if ($document !== null) {
                $documents[$index] = $document;
            }
        }

        return $documents;
    }

    private function archiveRow(FileResult $row, bool $copyIntoArchive): ?Document
    {
        $placed = $row->placed;

        // Rejected files were never placed: there is no file to index.
        if ($placed === null || ! is_file($placed->path)) {
            return null;
        }

        $checksum = hash_file('sha256', $placed->path) ?: null;
        $existing = $this->findExisting($placed, $checksum);

        $attributes = [
            'counterparty_id' => $this->counterpartyIdFor($placed->counterpartyRfc),
            'folio' => $placed->folio,
            'extension' => $placed->extension,
            'name' => basename($placed->path),
            'original_name' => $row->original,
            'issued_on' => $placed->issuedOn,
            'total' => $placed->total,
            'type_letter' => $placed->type->value,
            'confidence' => $placed->confidence->value,
            'source' => $placed->source?->value,
            'size' => filesize($placed->path) ?: null,
            'checksum' => $checksum,
            ...$copyIntoArchive
                ? ['disk' => Document::DISK, 'path' => $this->store($placed, $existing)]
                : ['disk' => null, 'path' => $placed->path],
        ];

        if ($existing !== null) {
            $existing->update($attributes);

            return $existing;
        }

        return Document::query()->create($attributes);
    }

    /**
     * The tax folio is the natural key. When there is none (a CFDI read from the
     * PDF by heuristics) the fallback is the file's contents.
     */
    private function findExisting(PlacedFile $placed, ?string $checksum): ?Document
    {
        $query = Document::query()->where('extension', $placed->extension);

        if ($placed->folio !== null && $placed->folio !== '') {
            return $query->where('folio', $placed->folio)->first();
        }

        if ($checksum === null) {
            return null;
        }

        return $query->whereNull('folio')->where('checksum', $checksum)->first();
    }

    private function counterpartyIdFor(?string $rfc): ?int
    {
        if ($rfc === null || $rfc === '') {
            return null;
        }

        // Generic RFCs resolve to the generic counterparty name without creating
        // a row, so there is nothing to find here and the document ends up
        // without a counterparty.
        return Counterparty::query()->where('rfc', $rfc)->value('id');
    }

    /**
     * Copies the file to the permanent disk and returns its relative path.
     */
    private function store(PlacedFile $placed, ?Document $existing): string
    {
        $disk = $this->disk();
        $target = $this->freePath($disk, $this->directoryFor($placed).'/'.basename($placed->path), $existing);

        $stream = fopen($placed->path, 'rb');

        if ($stream === false) {
            throw new RuntimeException(
                (string) __('cfdi.error.unreadable_file', ['path' => $placed->path]),
            );
        }

        $disk->writeStream($target, $stream);
        fclose($stream);

        // If the name changed (a different template, a different counterparty
        // name) the old file is orphaned: it has to be deleted here or never.
        if ($existing !== null && $existing->isArchived() && $existing->path !== $target) {
            $disk->delete($existing->path);
        }

        return $target;
    }

    private function directoryFor(PlacedFile $placed): string
    {
        $rfc = $placed->counterpartyRfc !== null && $placed->counterpartyRfc !== ''
            ? $placed->counterpartyRfc
            : self::NO_RFC;

        $year = $placed->issuedOn !== null && $placed->issuedOn !== ''
            ? substr($placed->issuedOn, 0, 4)
            : self::NO_DATE;

        return "{$rfc}/{$year}";
    }

    /**
     * `name (2).ext` when the slot is already taken by ANOTHER document.
     *
     * Overwriting the document's own path is exactly what we want when
     * reprocessing the same folio; overwriting someone else's would lose a file.
     */
    private function freePath(Filesystem $disk, string $target, ?Document $existing): string
    {
        if ($existing !== null && $existing->isArchived() && $existing->path === $target) {
            return $target;
        }

        if (! $disk->fileExists($target)) {
            return $target;
        }

        $stem = PathExpander::stem($target);
        $suffix = PathExpander::suffix($target);
        $directory = dirname($target);

        for ($counter = 2; ; $counter++) {
            $candidate = "{$directory}/{$stem} ({$counter}){$suffix}";

            if (! $disk->fileExists($candidate)) {
                return $candidate;
            }
        }
    }

    private function disk(): Filesystem
    {
        return Storage::disk(Document::DISK);
    }
}
