<?php

namespace App\Cloud\Data;

/**
 * One file about to be written to the provider.
 *
 * `localPath` is always absolute and always readable: the caller resolved it
 * through `Document::absolutePath()`, which covers both the archived copy (web)
 * and the file sitting wherever `--output` put it (command).
 *
 * `remoteId` is the provider's id for the copy already there, when there is
 * one. It is what turns this upload into a replacement, which is what keeps
 * reprocessing the same folio from leaving a second copy behind.
 */
final readonly class CloudUpload
{
    public function __construct(
        public string $localPath,
        public string $name,
        public string $mimeType,
        public CloudDestination $destination,
        public ?string $remoteId = null,
    ) {}
}
