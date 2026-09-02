<?php

namespace App\Drive\Data;

/**
 * A file that now exists in Drive.
 *
 * `webViewLink` is what the results table links to. Drive only returns it when
 * it is asked for explicitly (`fields=id,webViewLink`).
 */
final readonly class DriveFile
{
    public function __construct(
        public string $id,
        public string $webViewLink,
    ) {}
}
