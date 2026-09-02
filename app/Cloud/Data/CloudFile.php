<?php

namespace App\Cloud\Data;

/**
 * A file that now exists at the provider.
 *
 * `id` is what a later run sends back as `CloudUpload::$remoteId` to replace
 * this same file; `link` is what the results table and the documents list open.
 */
final readonly class CloudFile
{
    public function __construct(
        public string $id,
        public string $link,
    ) {}
}
