<?php

namespace App\Cfdi\Data;

/**
 * PDF + XML con el mismo nombre base: una sola factura.
 */
final class FileGroup
{
    public function __construct(
        public string $stem,
        public ?string $xml = null,
        public ?string $pdf = null,
    ) {}

    /**
     * Los archivos del grupo, XML primero. El orden importa: define el orden de
     * las filas y de las advertencias.
     *
     * @return list<string>
     */
    public function files(): array
    {
        return array_values(array_filter([$this->xml, $this->pdf], fn (?string $p): bool => $p !== null));
    }
}
