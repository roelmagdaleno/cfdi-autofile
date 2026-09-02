<?php

namespace App\Cfdi\Data;

final class CollectionError
{
    public function __construct(
        public readonly string $subject,
        public readonly string $reason,
    ) {}
}
