<?php

namespace App\Cfdi\Data;

/**
 * Todo lo que se sabe de un archivo que SI se copio o movio.
 *
 * Existe para que el procesador no tenga que conocer Eloquent: se limita a
 * devolver los datos, y quien lo llamo decide si los archiva. Los rechazados
 * nunca traen uno.
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
