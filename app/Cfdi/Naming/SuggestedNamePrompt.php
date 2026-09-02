<?php

namespace App\Cfdi\Naming;

/**
 * No pregunta nada: usa la sugerencia automatica.
 *
 * Lo usan el comando con `--no-interactive` y la web (que ya pregunto antes).
 */
final class SuggestedNamePrompt implements CounterpartyNamePrompt
{
    public function askName(string $rfc, ?string $legalName, string $suggestion): string
    {
        return $suggestion;
    }
}
