<?php

namespace App\Drive\Exceptions;

/**
 * The file cannot be filed: there is no year to build the folder name from, or
 * the user's RFC is not a party so it is neither income nor an expense.
 *
 * This is a `skipped`, not a `failed`: retrying cannot change the answer.
 */
final class DriveDestinationUnknownException extends DriveException
{
    public static function missingDate(): self
    {
        return new self((string) __('cfdi.drive.skipped.no_date'));
    }

    public static function unknownType(): self
    {
        return new self((string) __('cfdi.drive.skipped.unknown_type'));
    }
}
