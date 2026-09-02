<?php

namespace App\Drive\Data;

/**
 * The part of the Drive integration the settings form edits.
 *
 * It deliberately carries no credentials: the refresh token is read and written
 * through its own accessors on `AppSettings`, so a form submission can never
 * blank it out by accident.
 */
final readonly class DriveConfig
{
    /** Drive's own alias for the top of "My Drive". */
    public const string ROOT = 'root';

    public const string DEFAULT_INCOME_FOLDER = '{year} - Emitidas';

    public const string DEFAULT_EXPENSE_FOLDER = '{year} - Recibidas';

    public function __construct(
        public bool $enabled,
        public string $rootFolderId,
        public string $incomeFolder,
        public string $expenseFolder,
    ) {}

    public static function defaults(): self
    {
        return new self(
            enabled: false,
            rootFolderId: self::ROOT,
            incomeFolder: self::DEFAULT_INCOME_FOLDER,
            expenseFolder: self::DEFAULT_EXPENSE_FOLDER,
        );
    }
}
