<?php

namespace App\Cfdi\Naming;

/**
 * The interaction seam: it is what keeps the orchestrator free of both the
 * terminal and HTTP. There is no `interactive` flag anywhere in the core.
 */
interface CounterpartyNamePrompt
{
    /**
     * Name to use for the counterparty.
     *
     * Implementations must never throw, and must fall back to `$suggestion` when
     * the user answers nothing.
     */
    public function askName(string $rfc, ?string $legalName, string $suggestion): string;
}
