<?php

namespace App\Cfdi\Data;

enum FileAction: string
{
    case Copy = 'copied';

    case Move = 'moved';

    public function label(): string
    {
        return (string) __("cfdi.action.{$this->value}");
    }
}
