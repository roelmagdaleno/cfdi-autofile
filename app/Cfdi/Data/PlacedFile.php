<?php

namespace App\Cfdi\Data;

/**
 * Everything known about a file that WAS copied or moved.
 *
 * It exists so the processor does not have to know about Eloquent: it just
 * returns the data and the caller decides whether to archive it. Rejected files
 * never carry one.
 */
final class PlacedFile
{
    public function __construct(
        public readonly string $path,
        public readonly string $extension,
        public readonly ?string $folio,
        public readonly ?string $issuedOn,
        public readonly ?string $total,
        public readonly TypeLetter $type,
        public readonly ?string $counterpartyRfc,
        public readonly string $counterpartyName,
        public readonly Confidence $confidence,
        public readonly ?SourceFormat $source,
    ) {}
}
