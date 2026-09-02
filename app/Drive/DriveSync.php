<?php

namespace App\Drive;

use App\Cfdi\Data\TypeLetter;
use App\Drive\Contracts\DriveFiles;
use App\Drive\Data\DriveFile;
use App\Drive\Data\DriveOutcome;
use App\Drive\Data\DriveStatus;
use App\Drive\Data\DriveSyncResult;
use App\Drive\Data\DriveUpload;
use App\Drive\Exceptions\DriveDestinationUnknownException;
use App\Drive\Exceptions\DriveException;
use App\Models\Document;
use App\Support\AppSettings;

/**
 * Uploads the documents a run just archived, and records where they landed.
 *
 * This is the only thing the two entry points -- the Livewire run and the
 * Artisan command -- know about Drive. It works from `Document` rows rather than
 * from the processor's output, because a document already carries the two
 * coordinates that decide the folder (`type_letter`, `issued_on`) and the
 * absolute path of the file in either mode, archived copy or file in place.
 *
 * Nothing in here is allowed to abort a run: a failure is a value, not an
 * exception. The renaming already happened and the files are already on disk, so
 * losing that over a network blip would be absurd.
 */
final class DriveSync
{
    /** @var array<string, string> */
    private const array MIME_TYPES = [
        'xml' => 'application/xml',
        'pdf' => 'application/pdf',
    ];

    private const string FALLBACK_MIME_TYPE = 'application/octet-stream';

    public function __construct(
        private readonly AppSettings $settings,
        private readonly DriveFiles $files,
    ) {}

    /**
     * @param  array<int, Document>  $documents  as `DocumentArchiver::archive()` returns them
     */
    public function sync(array $documents): DriveSyncResult
    {
        $config = $this->settings->drive();

        // Off, or nothing to do: not a single byte reaches the network.
        if (! $config->enabled || $documents === []) {
            return new DriveSyncResult;
        }

        // Checked once, up front: without a token every single file would fail
        // for the same reason, and twenty identical rows help nobody.
        if (! $this->settings->isDriveConnected()) {
            return $this->allFailed($documents, (string) __('cfdi.drive.not_connected'));
        }

        $resolver = new DriveFolderResolver($this->files, $config);
        $outcomes = [];

        foreach ($documents as $key => $document) {
            $outcomes[$key] = $this->syncOne($resolver, $document);
        }

        return new DriveSyncResult($outcomes, $this->warningsFor($outcomes));
    }

    private function syncOne(DriveFolderResolver $resolver, Document $document): DriveOutcome
    {
        // The command indexes files that live on the user's disk, so one can be
        // moved or deleted between the run and the upload.
        if (! $document->fileExists()) {
            return DriveOutcome::skipped((string) __('cfdi.drive.skipped.missing_file'));
        }

        try {
            $folderId = $resolver->folderIdFor(
                $this->typeOf($document),
                $document->issued_on?->format('Y-m-d'),
            );
        } catch (DriveDestinationUnknownException $exception) {
            return DriveOutcome::skipped($exception->getMessage());
        }

        $upload = new DriveUpload(
            localPath: $document->absolutePath(),
            name: $document->name,
            mimeType: self::MIME_TYPES[$document->extension] ?? self::FALLBACK_MIME_TYPE,
            folderId: $folderId,
        );

        try {
            $file = $this->put($document, $upload);
        } catch (DriveException $exception) {
            return DriveOutcome::failed($exception->getMessage());
        }

        $document->update([
            'drive_file_id' => $file->id,
            'drive_link' => $file->webViewLink,
            'drive_uploaded_at' => now(),
        ]);

        return DriveOutcome::uploaded($file->webViewLink);
    }

    /**
     * Reprocessing the same CFDI must update the file in Drive, not add a
     * second copy of it. `drive_file_id` is what makes that possible, riding on
     * the deduplication `documents` already does by folio.
     *
     * If that id no longer resolves -- the user deleted the file from Drive --
     * the upload starts over instead of failing, which is the only outcome that
     * leaves them with their invoice.
     */
    private function put(Document $document, DriveUpload $upload): DriveFile
    {
        if ($document->drive_file_id === null) {
            return $this->files->upload($upload);
        }

        try {
            return $this->files->replace($document->drive_file_id, $upload);
        } catch (DriveException) {
            return $this->files->upload($upload);
        }
    }

    private function typeOf(Document $document): TypeLetter
    {
        return TypeLetter::tryFrom((string) $document->type_letter) ?? TypeLetter::Unknown;
    }

    /**
     * @param  array<int, Document>  $documents
     */
    private function allFailed(array $documents, string $reason): DriveSyncResult
    {
        $outcomes = [];

        foreach (array_keys($documents) as $key) {
            $outcomes[$key] = DriveOutcome::failed($reason);
        }

        return new DriveSyncResult($outcomes, [$reason]);
    }

    /**
     * One summarizing warning, not one per row: the per-file reason is already
     * on its own row in the results table.
     *
     * @param  array<int, DriveOutcome>  $outcomes
     * @return list<string>
     */
    private function warningsFor(array $outcomes): array
    {
        $failed = array_filter($outcomes, fn (DriveOutcome $o): bool => $o->status === DriveStatus::Failed);

        if ($failed === []) {
            return [];
        }

        return [(string) __('cfdi.drive.warning.some_failed', ['count' => count($failed)])];
    }
}
