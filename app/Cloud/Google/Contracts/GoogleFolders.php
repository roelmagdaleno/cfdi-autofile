<?php

namespace App\Cloud\Google\Contracts;

use App\Cloud\Exceptions\CloudException;

/**
 * Drive's folder tree, which is Drive's alone.
 *
 * This is deliberately NOT part of `CloudFiles`: "make sure the folder called
 * X exists under parent Y and give me its id" has no meaning for a path-based
 * provider, where the prefix of a key is not an object that can exist or not.
 * Putting it in the shared contract would force S3 to implement a lie.
 */
interface GoogleFolders
{
    /**
     * Id of the `$name` sub-folder of `$parentId`, creating it when missing.
     *
     * @throws CloudException
     */
    public function ensureFolder(string $parentId, string $name): string;
}
