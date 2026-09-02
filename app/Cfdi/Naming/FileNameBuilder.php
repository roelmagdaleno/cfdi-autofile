<?php

namespace App\Cfdi\Naming;

use App\Cfdi\Data\CfdiData;
use App\Cfdi\Data\TypeLetter;

/**
 * Applies the template and sanitizes the result.
 *
 * An unknown placeholder is left literal: `{folio}` comes out as `{folio}`.
 * Unlike the original (which uses `format_map`), a malformed template never
 * blows up: `{{date}}`, an unclosed brace or a format spec are let through.
 * The web needs it that way.
 */
final class FileNameBuilder
{
    public function __construct(private readonly NameSanitizer $sanitizer) {}

    public function build(
        CfdiData $data,
        TypeLetter $type,
        string $counterparty,
        string $template,
        string $extension,
        ?TypeLetters $letters = null,
    ): string {
        // The letters are configurable, so `{type}` renders whatever the user
        // set (`E`/`R`, for instance) while `$type` keeps its canonical value.
        $letters ??= TypeLetters::defaults();

        $values = [
            Placeholder::Date->value => self::formatDate($data->date),
            Placeholder::Type->value => $letters->for($type),
            Placeholder::Counterparty->value => $counterparty,
            Placeholder::Amount->value => $this->formatAmount($data->total),
            Placeholder::Uuid->value => $data->uuid ?? '',
            Placeholder::CreditNote->value => $data->isCreditNote() ? self::creditNoteSuffix() : '',
        ];

        // Sanitizing runs AFTER substitution, over the whole name. That is why a
        // type letter of `?` ends up converted into `-`.
        $name = $this->sanitizer->sanitize($this->render($template, $values));

        return ($name !== '' ? $name : 'cfdi').$extension;
    }

    /**
     * The suffix appended to a credit note's file name.
     *
     * It is translated, so it follows the active locale: this is one of the few
     * strings that reaches the user's disk rather than just the screen.
     */
    public static function creditNoteSuffix(): string
    {
        return (string) __('cfdi.credit_note_suffix');
    }

    /**
     * The issue date as `YYYY-MM-DD`, or an empty string.
     *
     * Public and static because archiving needs it to fill `documents.issued_on`
     * with exactly the same value that shows up in the name.
     */
    public static function formatDate(?string $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        return trim(explode('T', $date)[0]);
    }

    /**
     * The total with two decimals, or `null` when it is not a number.
     *
     * It uses `sprintf('%.2F')` and not `number_format()`.
     *
     * Python formats the exact binary double (banker's rounding); `number_format`
     * rounds the decimal representation up. They differ on ties: `2.675` gives
     * `2.67` in Python and `2.68` with `number_format`.
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
     * A non-numeric total is let through sanitized: the name never invents an
     * amount, but it does not lose the raw value either.
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
