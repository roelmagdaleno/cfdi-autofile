<?php

namespace App\Cfdi\Naming;

final class CounterpartyNameSuggester
{
    /**
     * Legal forms and connectors stripped from the automatic suggestion.
     *
     * These stay in Spanish on purpose: they match the Mexican legal-entity
     * names that come in the CFDI's `Emisor@Nombre` attribute.
     *
     * `S.A.` and `S.A` can never match, because the lookup key is the word with
     * its dots removed. They are kept for parity with the original.
     *
     * @var array<string, true>
     */
    public const array LEGAL_FORMS = [
        'SA' => true, 'S' => true, 'A' => true, 'DE' => true, 'CV' => true,
        'C' => true, 'V' => true, 'SAPI' => true, 'SAB' => true, 'SC' => true,
        'AC' => true, 'SRL' => true, 'RL' => true, 'SDE' => true,
        'S.A.' => true, 'S.A' => true, 'SAS' => true, 'SOFOM' => true,
        'ENR' => true, 'MI' => true, 'SPR' => true, 'SNC' => true,
        'SAPIB' => true, 'B' => true, 'BURSATIL' => true, 'CAPITAL' => true,
        'VARIABLE' => true, 'ANONIMA' => true, 'SOCIEDAD' => true,
        'PROMOTORA' => true, 'INVERSION' => true, 'RESPONSABILIDAD' => true,
        'LIMITADA' => true, 'CIVIL' => true, 'LTD' => true, 'LLC' => true,
        'INC' => true, 'CORP' => true,
        // Connectors that add nothing to the short name.
        'DEL' => true, 'LA' => true, 'LAS' => true, 'LOS' => true,
        'EL' => true, 'Y' => true, 'E' => true,
    ];

    public function __construct(private readonly NameSanitizer $sanitizer) {}

    /**
     * Legal name without its legal form, at most 3 words, without accents.
     */
    public function suggest(?string $legalName): string
    {
        if ($legalName === null || $legalName === '') {
            return '';
        }

        $clean = $this->sanitizer->stripAccents($legalName);
        $clean = (string) preg_replace('/[^\w\s&.]/u', ' ', $clean);

        /** @var list<string> $all */
        $all = preg_split('/\s+/u', trim($clean), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $words = [];

        foreach ($all as $word) {
            $base = mb_strtoupper(str_replace('.', '', $word));

            if ($base === '' || isset(self::LEGAL_FORMS[$base])) {
                continue;
            }

            $words[] = trim($word, '.');
        }

        if ($words === []) {
            $words = $all;
        }

        return trim(implode(' ', array_slice($words, 0, 3)));
    }
}
