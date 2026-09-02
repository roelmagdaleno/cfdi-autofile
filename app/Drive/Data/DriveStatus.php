<?php

namespace App\Drive\Data;

/**
 * What happened to one file on its way to Google Drive.
 *
 * `Skipped` is not a failure: it is the "there is no folder this file could
 * possibly belong to" case (a CFDI with no issue date, or one where the user's
 * RFC is not a party, so it is neither income nor an expense). Nothing is
 * retried for it, because retrying would produce the same answer.
 *
 * `Failed`, on the other hand, is transient by assumption -- a network blip, an
 * expired authorization -- which is why the results screen offers a retry.
 */
enum DriveStatus: string
{
    case Uploaded = 'uploaded';

    case Skipped = 'skipped';

    case Failed = 'failed';

    public function label(): string
    {
        return (string) __('ui.drive.status.'.$this->value);
    }

    public function isRetryable(): bool
    {
        return $this === self::Failed;
    }
}
