<?php

namespace App\Cfdi\Naming;

use Normalizer;

final class NameSanitizer
{
    /**
     * The `ñ` is replaced BEFORE normalizing, just like in the original.
     */
    public function stripAccents(string $text): string
    {
        $text = str_replace(['ñ', 'Ñ'], ['n', 'N'], $text);

        $decomposed = Normalizer::normalize($text, Normalizer::FORM_D);

        if ($decomposed === false) {
            $decomposed = $text;
        }

        return (string) preg_replace('/\p{Mn}/u', '', $decomposed);
    }

    /**
     * Strips commas, dollar signs, accents and ñ; leaves the name usable as a
     * file name.
     */
    public function sanitize(string $text): string
    {
        $text = $this->stripAccents($text);
        $text = str_replace([',', '$'], '', $text);
        $text = (string) preg_replace('/[\/\\\\:*?"<>|]/u', '-', $text);
        $text = (string) preg_replace('/\s+/u', ' ', $text);

        // Two separate trims, in this order: Python's `.strip().strip('.')`
        // leaves `"a. ."` as `"a. "`, with the trailing space intact.
        return trim(trim($text), '.');
    }
}
