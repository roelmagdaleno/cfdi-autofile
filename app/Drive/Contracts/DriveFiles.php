<?php

namespace App\Drive\Contracts;

use App\Drive\Data\DriveFile;
use App\Drive\Data\DriveUpload;
use App\Drive\Exceptions\DriveException;

/**
 * The whole surface of Google Drive this application needs.
 *
 * It exists for the same reason `PdfTextExtractor` does: it is the seam the
 * tests replace, so no test can reach the network and the SDK-shaped details
 * stay inside one implementation.
 */
interface DriveFiles
{
    /**
     * Id of the `$name` sub-folder of `$parentId`, creating it when missing.
     *
     * @throws DriveException
     */
    public function ensureFolder(string $parentId, string $name): string;

    /**
     * @throws DriveException
     */
    public function upload(DriveUpload $upload): DriveFile;

    /**
     * Overwrites the contents of an existing file, renaming and moving it when
     * the template or the counterparty produced a different destination.
     *
     * @throws DriveException
     */
    public function replace(string $fileId, DriveUpload $upload): DriveFile;

    /**
     * Address of the connected account. It doubles as the connection test.
     *
     * @throws DriveException
     */
    public function accountEmail(): string;
}
