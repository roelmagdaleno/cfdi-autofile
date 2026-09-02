<?php

namespace App\Cloud\Google;

/**
 * Where in Drive the files go: the folder that holds the year folders, and the
 * names of the two year folders themselves.
 *
 * All of it is Drive's own shape, which is why it is configured apart from
 * `CloudConfig` and read through its own accessor. It deliberately carries no
 * credentials either: the refresh token has its own accessors, so a form
 * submission can never blank it out by accident.
 */
final readonly class GoogleDriveConfig
{
    /** Drive's own alias for the top of "My Drive". */
    public const string ROOT = 'root';

    public const string DEFAULT_INCOME_FOLDER = '{year} - Emitidas';

    public const string DEFAULT_EXPENSE_FOLDER = '{year} - Recibidas';

    public function __construct(
        public string $rootFolderId,
        public string $incomeFolder,
        public string $expenseFolder,
    ) {}

    public static function defaults(): self
    {
        return new self(
            rootFolderId: self::ROOT,
            incomeFolder: self::DEFAULT_INCOME_FOLDER,
            expenseFolder: self::DEFAULT_EXPENSE_FOLDER,
        );
    }
}
