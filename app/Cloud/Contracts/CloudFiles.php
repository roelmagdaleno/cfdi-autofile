<?php

namespace App\Cloud\Contracts;

use App\Cloud\Data\CloudFile;
use App\Cloud\Data\CloudUpload;
use App\Cloud\Exceptions\CloudException;

/**
 * The whole surface of a cloud storage provider this application needs.
 *
 * It exists for the same reason `PdfTextExtractor` does: it is the seam the
 * tests replace, so no test can reach the network and the vendor-shaped details
 * stay inside one implementation.
 *
 * Nothing provider-specific is allowed in here. A folder id, an OAuth scope or
 * a bucket name would make the contract a lie for the next provider, so those
 * live behind the provider's own interfaces instead.
 */
interface CloudFiles
{
    /**
     * Writes the file, replacing the provider's own copy when the upload
     * carries a `remoteId`.
     *
     * How an overwrite is performed is deliberately the provider's business:
     * S3 writing the same key IS the replacement, Dropbox asks for
     * `mode=overwrite`, and Drive needs a PATCH that also moves the file. So is
     * the recovery when the stored id no longer resolves.
     *
     * @throws CloudException
     */
    public function put(CloudUpload $upload): CloudFile;

    /**
     * Human-readable name of the connected account or target -- an address, a
     * bucket. It doubles as the connection test.
     *
     * @throws CloudException
     */
    public function accountLabel(): string;
}
