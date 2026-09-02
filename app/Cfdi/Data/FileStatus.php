<?php

namespace App\Cfdi\Data;

enum FileStatus: string
{
    case Ok = 'ok';

    case Review = 'review';

    case Rejected = 'rejected';

    public function label(): string
    {
        return (string) __("cfdi.status.{$this->value}");
    }

    /**
     * Label for a raw value read back from `run_files.status`.
     */
    public static function labelFor(string $status): string
    {
        return self::tryFrom($status)?->label() ?? $status;
    }
}
