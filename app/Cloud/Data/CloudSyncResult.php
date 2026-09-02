<?php

namespace App\Cloud\Data;

/**
 * What a whole sync did, shaped like `ProcessResult`: the per-file rows plus the
 * run-level warnings.
 *
 * The outcome keys are the caller's own keys, untouched, so a run can hand over
 * its index-keyed documents and get index-keyed outcomes back.
 *
 * The provider rides on the result rather than on each outcome: the outcomes
 * stay pure per-file values, and the callers that freeze a snapshot of the run
 * -- or name the provider in a heading -- read it from here. It is `null` when
 * nothing was synced at all, which is also when nobody asks.
 */
final readonly class CloudSyncResult
{
    /**
     * @param  array<int, CloudOutcome>  $outcomes
     * @param  list<string>  $warnings
     */
    public function __construct(
        public array $outcomes = [],
        public array $warnings = [],
        public ?CloudProvider $provider = null,
    ) {}
}
