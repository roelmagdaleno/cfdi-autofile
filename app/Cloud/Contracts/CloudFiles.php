<?php

namespace App\Cloud\Contracts;

use App\Cloud\Data\CloudFile;
use App\Cloud\Data\CloudUpload;
use App\Cloud\Exceptions\CloudException;

/**
 * The whole surface of Google Drive this application needs.
 *
 * It exists for the same reason `PdfTextExtractor` does: it is the seam the
 * tests replace, so no test can reach the network and the SDK-shaped details
 * stay inside one implementation.
 */
interface CloudFiles
{
    /**
     * Id of the `$name` sub-folder of `$parentId`, creating it when missing.
     *
     * @throws CloudException
     */
    public function ensureFolder(string $parentId, string $name): string;

    /**
     * @throws CloudException
     */
    public function upload(CloudUpload $upload): CloudFile;

    /**
     * Overwrites the contents of an existing file, renaming and moving it when
     * the template or the counterparty produced a different destination.
     *
     * @throws CloudException
     */
    public function replace(string $fileId, CloudUpload $upload): CloudFile;

    /**
     * Address of the connected account. It doubles as the connection test.
     *
     * @throws CloudException
     */
    public function accountEmail(): string;
}
