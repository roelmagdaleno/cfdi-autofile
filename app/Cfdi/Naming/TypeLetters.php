<?php

namespace App\Cfdi\Naming;

use App\Cfdi\Data\TypeLetter;

/**
 * The labels written into a file name for each type of CFDI.
 *
 * `TypeLetter` keeps `I`/`G`/`?` as the canonical identity (that is what
 * `documents.type_letter` stores and what the filters compare against). This
 * object is the only place that turns that identity into the text the user sees
 * on disk, so somebody who thinks in "emitido / recibido" can write `E` and `R`
 * without the archive losing track of what is income and what is an expense.
 */
final readonly class TypeLetters
{
    /** How many characters a label may have. */
    public const int MAX_LENGTH = 8;

    public function __construct(
        public string $income,
        public string $expense,
    ) {}

    public static function defaults(): self
    {
        return new self(TypeLetter::Income->value, TypeLetter::Expense->value);
    }

    /**
     * `Unknown` is deliberately not configurable: it is the "your RFC is not on
     * this invoice" case, and `NameSanitizer` turns its `?` into a `-` anyway.
     */
    public function for(TypeLetter $type): string
    {
        return match ($type) {
            TypeLetter::Income => $this->income,
            TypeLetter::Expense => $this->expense,
            TypeLetter::Unknown => TypeLetter::Unknown->value,
        };
    }

    /**
     * Whether a label can be used as-is.
     *
     * The forbidden characters are the ones `NameSanitizer` replaces with `-`:
     * accepting them would silently write something other than what was typed.
     * Shared by the settings screen and the Artisan command so both reject the
     * same input.
     */
    public static function isValid(string $letter): bool
    {
        $letter = trim($letter);

        if ($letter === '' || mb_strlen($letter) > self::MAX_LENGTH) {
            return false;
        }

        return preg_match('/[\/\\\\:*?"<>|]/u', $letter) !== 1;
    }
}
