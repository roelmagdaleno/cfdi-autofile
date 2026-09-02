<?php

namespace App\Cfdi\Exceptions;

use RuntimeException;

/**
 * The file is not a valid CFDI, or it is missing indispensable data.
 */
final class CfdiRejectedException extends RuntimeException
{
    //
}
