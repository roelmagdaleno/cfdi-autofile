<?php

namespace App\Cfdi\Naming;

/**
 * Catalog of the name template placeholders.
 *
 * This is the single source of truth: the keys substituted by
 * `FileNameBuilder`, the template validation rule and the pills drawn by the
 * settings screen all come from here.
 */
enum Placeholder: string
{
    case Date = 'date';

    case Type = 'type';

    case Counterparty = 'counterparty';

    case Amount = 'amount';

    case Uuid = 'uuid';

    case CreditNote = 'credit_note';

    /** The placeholder as it is written inside a template. */
    public function token(): string
    {
        return '{'.$this->value.'}';
    }

    public function description(): string
    {
        return (string) __("cfdi.placeholder.{$this->value}");
    }

    /**
     * Splits the template into literal text and known placeholders.
     *
     * A `{placeholder}` that is not in the catalog is returned as text, exactly
     * the way `FileNameBuilder` treats it.
     *
     * @return list<array{placeholder: self|null, text: string}>
     */
    public static function segments(string $template): array
    {
        $pieces = preg_split(self::pattern(), $template, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($pieces === false) {
            return [['placeholder' => null, 'text' => $template]];
        }

        $segments = [];

        // `PREG_SPLIT_DELIM_CAPTURE` interleaves the captured group (the
        // placeholder name, without braces) between the chunks of text, so the
        // odd indexes are always placeholders. Looking at the position instead
        // of the value avoids confusing literal text that happens to read the
        // same, such as the template `date`.
        foreach ($pieces as $index => $piece) {
            $placeholder = $index % 2 === 1 ? self::from($piece) : null;

            if ($placeholder instanceof self) {
                $segments[] = ['placeholder' => $placeholder, 'text' => $placeholder->token()];

                continue;
            }

            if ($piece !== '') {
                $segments[] = ['placeholder' => null, 'text' => $piece];
            }
        }

        return $segments;
    }

    /**
     * Pattern that detects any known placeholder inside a template.
     */
    public static function pattern(): string
    {
        $names = array_map(
            static fn (self $placeholder): string => preg_quote($placeholder->value, '/'),
            self::cases(),
        );

        return '/\{('.implode('|', $names).')\}/u';
    }
}
