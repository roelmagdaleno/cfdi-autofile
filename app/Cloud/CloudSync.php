<?php

namespace App\Cloud;

use App\Cfdi\Data\TypeLetter;
use App\Cloud\Contracts\CloudDestinations;
use App\Cloud\Contracts\CloudFiles;
use App\Cloud\Data\CloudOutcome;
use App\Cloud\Data\CloudProvider;
use App\Cloud\Data\CloudStatus;
use App\Cloud\Data\CloudSyncResult;
use App\Cloud\Data\CloudUpload;
use App\Cloud\Exceptions\CloudDestinationUnknownException;
use App\Cloud\Exceptions\CloudException;
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
final class CloudSync
{
    /** @var array<string, string> */
    private const array MIME_TYPES = [
        'xml' => 'application/xml',
        'pdf' => 'application/pdf',
    ];

    private const string FALLBACK_MIME_TYPE = 'application/octet-stream';

    public function __construct(
        private readonly AppSettings $settings,
        private readonly CloudFiles $files,
        private readonly CloudDestinations $destinations,
    ) {}

    /**
     * @param  array<int, Document>  $documents  as `DocumentArchiver::archive()` returns them
     */
    public function sync(array $documents): CloudSyncResult
    {
        $config = $this->settings->cloud();

        // Off, or nothing to do: not a single byte reaches the network.
        if (! $config->enabled || $documents === []) {
            return new CloudSyncResult;
        }

        // Checked once, up front: without a token every single file would fail
        // for the same reason, and twenty identical rows help nobody.
        if (! $this->settings->isCloudConnected($config->provider)) {
            return $this->allFailed($documents, (string) __('cfdi.cloud.not_connected', [
                'provider' => $config->provider->label(),
            ]), $config->provider);
        }

        $provider = $config->provider;
        $outcomes = [];

        foreach ($documents as $key => $document) {
            $outcomes[$key] = $this->syncOne($document);
        }

        return new CloudSyncResult($outcomes, $this->warningsFor($outcomes, $provider), $provider);
    }

    private function syncOne(Document $document): CloudOutcome
    {
        // The command indexes files that live on the user's disk, so one can be
        // moved or deleted between the run and the upload.
        if (! $document->fileExists()) {
            return CloudOutcome::skipped((string) __('cfdi.cloud.skipped.missing_file'));
        }

        try {
            $destination = $this->destinations->destinationFor(
                $this->typeOf($document),
                $document->issued_on?->format('Y-m-d'),
            );
        } catch (CloudDestinationUnknownException $exception) {
            return CloudOutcome::skipped($exception->getMessage());
        }

        $upload = new CloudUpload(
            localPath: $document->absolutePath(),
            name: $document->name,
            mimeType: self::MIME_TYPES[$document->extension] ?? self::FALLBACK_MIME_TYPE,
            destination: $destination,
            remoteId: $document->drive_file_id,
        );

        try {
            $file = $this->files->put($upload);
        } catch (CloudException $exception) {
            return CloudOutcome::failed($exception->getMessage());
        }

        $document->update([
            'drive_file_id' => $file->id,
            'drive_link' => $file->link,
            'drive_uploaded_at' => now(),
        ]);

        return CloudOutcome::uploaded($file->link);
    }

    private function typeOf(Document $document): TypeLetter
    {
        return TypeLetter::tryFrom((string) $document->type_letter) ?? TypeLetter::Unknown;
    }

    /**
     * @param  array<int, Document>  $documents
     */
    private function allFailed(array $documents, string $reason, CloudProvider $provider): CloudSyncResult
    {
        $outcomes = [];

        foreach (array_keys($documents) as $key) {
            $outcomes[$key] = CloudOutcome::failed($reason);
        }

        return new CloudSyncResult($outcomes, [$reason], $provider);
    }

    /**
     * One summarizing warning, not one per row: the per-file reason is already
     * on its own row in the results table.
     *
     * @param  array<int, CloudOutcome>  $outcomes
     * @return list<string>
     */
    private function warningsFor(array $outcomes, CloudProvider $provider): array
    {
        $failed = array_filter($outcomes, fn (CloudOutcome $o): bool => $o->status === CloudStatus::Failed);

        if ($failed === []) {
            return [];
        }

        return [(string) __('cfdi.cloud.warning.some_failed', [
            'count' => count($failed),
            'provider' => $provider->label(),
        ])];
    }
}
