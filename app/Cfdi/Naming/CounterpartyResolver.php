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
     * Short name for the given RFC, cached in `counterparties`.
     */
    public function resolve(?string $rfc, ?string $legalName, CounterpartyNamePrompt $prompt): string
    {
        $rfc = Rfc::normalize($rfc);

        if (Rfc::isGeneric($rfc)) {
            return (string) __('cfdi.generic_counterparty');
        }

        // Without an RFC there is nothing to cache: we do not write a row with an
        // empty key.
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
     * A suggestion without persisting anything. The web's analysis phase uses it.
     */
    public function suggestionFor(string $rfc, ?string $legalName): string
    {
        return $this->suggester->suggest($legalName) ?: Rfc::normalize($rfc);
    }
}
