<?php

namespace App\Cfdi\Naming;

use App\Cfdi\Support\Rfc;
use App\Models\Counterparty;

final class CounterpartyResolver
{
    public function __construct(
        private readonly CounterpartyNameSuggester $suggester,
        private readonly NameSanitizer $sanitizer,
    ) {}

    /**
     * Nombre corto para el RFC dado, cacheado en `counterparties`.
     */
    public function resolve(?string $rfc, ?string $legalName, CounterpartyNamePrompt $prompt): string
    {
        $rfc = Rfc::normalize($rfc);

        if (Rfc::isGeneric($rfc)) {
            return 'General';
        }

        // Sin RFC no hay nada que cachear: no escribimos una fila con llave vacia.
        if ($rfc === '') {
            return $this->sanitizer->sanitize($this->suggester->suggest($legalName));
        }

        $cached = Counterparty::query()->where('rfc', $rfc)->first();

        if ($cached !== null && $cached->name !== '') {
            return $cached->name;
        }

        $suggestion = $this->suggester->suggest($legalName) ?: $rfc;
        $name = $this->sanitizer->sanitize($prompt->askName($rfc, $legalName, $suggestion)) ?: $rfc;

        Counterparty::query()->updateOrCreate(
            ['rfc' => $rfc],
            ['name' => $name, 'legal_name' => $legalName],
        );

        return $name;
    }

    public function isKnown(string $rfc): bool
    {
        $rfc = Rfc::normalize($rfc);

        return $rfc !== '' && Counterparty::query()
            ->where('rfc', $rfc)
            ->where('name', '!=', '')
            ->exists();
    }

    /**
     * Sugerencia sin persistir nada. La usa la fase de analisis de la web.
     */
    public function suggestionFor(string $rfc, ?string $legalName): string
    {
        return $this->suggester->suggest($legalName) ?: Rfc::normalize($rfc);
    }
}
