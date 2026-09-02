<?php

namespace App\Drive\Data;

/**
 * One file about to be written to Drive.
 *
 * `localPath` is always absolute and always readable: the caller resolved it
 * through `Document::absolutePath()`, which covers both the archived copy (web)
 * and the file sitting wherever `--output` put it (command).
 */
final readonly class DriveUpload
{
    public function __construct(
        public string $localPath,
        public string $name,
        public string $mimeType,
        public string $folderId,
    ) {}
}
