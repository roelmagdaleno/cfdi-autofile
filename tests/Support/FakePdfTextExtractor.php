<?php

namespace Tests\Support;

use App\Cfdi\Parsing\PdfTextExtractor;

final class FakePdfTextExtractor implements PdfTextExtractor
{
    public function __construct(private readonly string $text) {}

    public function extract(string $path): string
    {
        return $this->text;
    }
}
