<?php

namespace App\Drive\Exceptions;

/**
 * Drive answered with something other than success.
 *
 * The message keeps Google's own `error.message` when there is one: "File not
 * found: 1a2b3c" or "storageQuotaExceeded" is far more actionable than a status
 * code, and it is the text the results table shows.
 */
final class DriveRequestException extends DriveException
{
    public static function make(string $operation, int $status, string $detail): self
    {
        return new self((string) __('cfdi.drive.request_failed', [
            'operation' => $operation,
            'status' => $status,
            'detail' => $detail,
        ]));
    }
}
