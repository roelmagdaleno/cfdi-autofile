<?php

namespace App\Cloud\Exceptions;

use RuntimeException;

/**
 * Base of everything the Drive integration can go wrong with.
 *
 * `CloudSync` catches this one type, so a new failure mode added later is
 * contained by default instead of aborting a run.
 */
abstract class CloudException extends RuntimeException
{
    //
}
