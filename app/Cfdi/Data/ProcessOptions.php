<?php

namespace App\Cfdi\Data;

final class ProcessOptions
{
    public function __construct(
        public readonly string $myRfc,
        public readonly string $template,
        public readonly string $outputDirectory,
        public readonly bool $move = false,
    ) {}

    public function action(): FileAction
    {
        return $this->move ? FileAction::Move : FileAction::Copy;
    }
}
