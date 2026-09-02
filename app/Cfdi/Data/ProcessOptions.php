<?php

namespace App\Cfdi\Data;

use App\Cfdi\Naming\TypeLetters;

final class ProcessOptions
{
    public function __construct(
        public readonly string $myRfc,
        public readonly string $template,
        public readonly string $outputDirectory,
        public readonly bool $move = false,
        /**
         * A static call is not a valid initializer, so the default is spelled
         * out; it is the same pair `TypeLetters::defaults()` returns.
         */
        public readonly TypeLetters $letters = new TypeLetters(TypeLetter::Income->value, TypeLetter::Expense->value),
    ) {}

    public function action(): FileAction
    {
        return $this->move ? FileAction::Move : FileAction::Copy;
    }
}
