<?php

namespace App\Cfdi\Data;

final class CollectedFiles
{
    /**
     * @param  list<string>  $paths
     * @param  list<CollectionError>  $errors
     */
    public function __construct(
        public readonly array $paths,
        public readonly array $errors,
    ) {}
}
