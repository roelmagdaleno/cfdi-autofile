<?php

namespace App\Cfdi\Data;

final class CounterpartyRef
{
    public function __construct(
        public readonly ?string $rfc,
        public readonly ?string $legalName,
    ) {}
}
