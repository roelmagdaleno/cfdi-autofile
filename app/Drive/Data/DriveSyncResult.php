<?php

namespace App\Drive\Data;

/**
 * What a whole sync did, shaped like `ProcessResult`: the per-file rows plus the
 * run-level warnings.
 *
 * The outcome keys are the caller's own keys, untouched, so a run can hand over
 * its index-keyed documents and get index-keyed outcomes back.
 */
final readonly class DriveSyncResult
{
    /**
     * @param  array<int, DriveOutcome>  $outcomes
     * @param  list<string>  $warnings
     */
    public function __construct(
        public array $outcomes = [],
        public array $warnings = [],
    ) {}
}
