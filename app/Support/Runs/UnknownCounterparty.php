<?php

namespace App\Support\Runs;

final class UnknownCounterparty
{
    public function __construct(
        public readonly string $rfc,
        public readonly ?string $legalName,
        public readonly string $suggestion,
    ) {}
}
