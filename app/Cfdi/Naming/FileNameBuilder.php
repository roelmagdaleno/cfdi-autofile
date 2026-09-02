<?php

namespace App\Cfdi\Naming;

use App\Cfdi\Data\CfdiData;
use App\Cfdi\Data\TypeLetter;

/**
 * Aplica la plantilla y sanitiza el resultado.
 *
 * Un placeholder desconocido se deja literal: `{folio}` sale como `{folio}`.
 * A diferencia del original (que usa `format_map`), una plantilla mal formada
 * nunca revienta: `{{fecha}}`, una llave sin cerrar o un format spec se dejan
 * pasar. La web lo necesita asi.
 */
final class FileNameBuilder
{
    public const string CREDIT_NOTE_SUFFIX = ' (nota de credito)';

    public function __construct(private readonly NameSanitizer $sanitizer) {}

    public function build(
        CfdiData $data,
        TypeLetter $type,
        string $counterparty,
        string $template,
        string $extension,
    ): string {
        $values = [
            Placeholder::Fecha->value => self::formatDate($data->date),
            Placeholder::Tipo->value => $type->value,
            Placeholder::Contraparte->value => $counterparty,
            Placeholder::Monto->value => $this->formatAmount($data->total),
            Placeholder::Uuid->value => $data->uuid ?? '',
            Placeholder::NotaCredito->value => $data->isCreditNote() ? self::CREDIT_NOTE_SUFFIX : '',
        ];

        // El sanitize corre DESPUES de sustituir, sobre el nombre completo. Por eso
        // una letra de tipo `?` termina convertida en `-`.
        $name = $this->sanitizer->sanitize($this->render($template, $values));

        return ($name !== '' ? $name : 'cfdi').$extension;
    }

    /**
     * La fecha de emision en `YYYY-MM-DD`, o cadena vacia.
     *
     * Publica y estatica porque el archivado la necesita para poblar
     * `documents.issued_on` con exactamente el mismo valor que sale en el nombre.
     */
    public static function formatDate(?string $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        return trim(explode('T', $date)[0]);
    }

    /**
     * El total con dos decimales, o `null` si no es un numero.
     *
     * Se usa `sprintf('%.2F')` y no `number_format()`.
     *
     * Python formatea el double binario exacto (redondeo al par); `number_format`
     * redondea la representacion decimal hacia arriba. Difieren en los empates:
     * `2.675` da `2.67` en Python y `2.68` con `number_format`.
     */
    public static function numericAmount(?string $total): ?string
    {
        if ($total === null || $total === '') {
            return null;
        }

        $raw = trim(str_replace([',', '$'], '', $total));

        return is_numeric($raw) ? sprintf('%.2F', (float) $raw) : null;
    }

    /**
     * Un total que no es numerico se deja pasar sanitizado: el nombre nunca
     * inventa un monto, pero tampoco pierde el dato crudo.
     */
    private function formatAmount(?string $total): string
    {
        if ($total === null || $total === '') {
            return '';
        }

        return self::numericAmount($total) ?? $this->sanitizer->sanitize($total);
    }

    /**
     * @param  array<string, string>  $values
     */
    private function render(string $template, array $values): string
    {
        return (string) preg_replace_callback(
            '/\{([^{}]*)\}/u',
            fn (array $matches): string => array_key_exists($matches[1], $values)
                ? $values[$matches[1]]
                : $matches[0],
            $template,
        );
    }
}
