<?php

namespace App\Cfdi\Naming;

/**
 * Asks nothing: it uses the automatic suggestion.
 *
 * Used by the command with `--no-interactive` and by the web (which already
 * asked beforehand).
 */
final class SuggestedNamePrompt implements CounterpartyNamePrompt
{
    public function askName(string $rfc, ?string $legalName, string $suggestion): string
    {
        return $suggestion;
    }
}
