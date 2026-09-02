<?php

namespace App\Cfdi\Data;

enum TypeLetter: string
{
    /** Ingreso: mi RFC es el emisor. */
    case Income = 'I';

    /** Gasto: mi RFC es el receptor. */
    case Expense = 'G';

    /** Mi RFC no aparece en el comprobante. */
    case Unknown = '?';
}
