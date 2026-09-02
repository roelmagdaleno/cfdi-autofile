<?php

namespace App\Cfdi\Data;

enum FileAction: string
{
    case Copy = 'copiado';
    case Move = 'movido';
}
