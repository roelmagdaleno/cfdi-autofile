<?php

namespace App\Cfdi\Data;

/**
 * A PDF + XML sharing the same base name: a single invoice.
 */
final class FileGroup
{
    public function __construct(
        public string $stem,
        public ?string $xml = null,
        public ?string $pdf = null,
    ) {}

    /**
     * The files of the group, XML first. The order matters: it defines the order
     * of the rows and of the warnings.
     *
     * @return list<string>
     */
    public function files(): array
    {
        return array_values(array_filter([$this->xml, $this->pdf], fn (?string $p): bool => $p !== null));
    }
}
