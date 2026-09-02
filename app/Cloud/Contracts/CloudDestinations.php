<?php

namespace App\Cloud\Contracts;

use App\Cfdi\Data\TypeLetter;
use App\Cloud\Data\CloudDestination;
use App\Cloud\Exceptions\CloudDestinationUnknownException;

/**
 * Turns "what kind of CFDI is this, and when was it issued" into the place the
 * file belongs in, whatever "place" means for the provider.
 *
 * The two coordinates are the ones the processor already produces: the issue
 * date gives the year, and `TypeLetter` says which of the two destinations of
 * that year -- income means the user's RFC is the issuer, so the invoice was
 * one they emitted.
 *
 * A folder-based provider resolves ids over the network here; a path-based one
 * (S3, Dropbox) just builds a string and never leaves the machine.
 */
interface CloudDestinations
{
    /**
     * @throws CloudDestinationUnknownException when there is no destination this
     *                                          file could belong to
     */
    public function destinationFor(TypeLetter $type, ?string $issuedOn): CloudDestination;
}
