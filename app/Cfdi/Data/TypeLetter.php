<?php

namespace App\Cfdi\Data;

enum TypeLetter: string
{
    /** Income: my RFC is the issuer. */
    case Income = 'I';

    /** Expense: my RFC is the recipient. */
    case Expense = 'G';

    /** My RFC does not appear on the invoice. */
    case Unknown = '?';
}
