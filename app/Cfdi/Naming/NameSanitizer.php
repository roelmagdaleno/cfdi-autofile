<?php

namespace App\Cfdi\Naming;

use Normalizer;

final class NameSanitizer
{
    /**
     * La `ñ` se reemplaza ANTES de normalizar, igual que en el original.
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
     * Quita comas, signos de peso, acentos y ñ; deja el nombre usable como archivo.
     */
    public function sanitize(string $text): string
    {
        $text = $this->stripAccents($text);
        $text = str_replace([',', '$'], '', $text);
        $text = (string) preg_replace('/[\/\\\\:*?"<>|]/u', '-', $text);
        $text = (string) preg_replace('/\s+/u', ' ', $text);

        // Dos trims separados y en este orden: `.strip().strip('.')` de Python
        // deja `"a. ."` como `"a. "`, con el espacio final intacto.
        return trim(trim($text), '.');
    }
}
