<?php

namespace App\Cfdi\Exceptions;

use RuntimeException;

/**
 * El archivo no es un CFDI valido, o le faltan datos indispensables.
 */
final class CfdiRejectedException extends RuntimeException
{
    //
}
