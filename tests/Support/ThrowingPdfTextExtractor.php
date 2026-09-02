<?php

namespace Tests\Support;

use App\Cfdi\Parsing\PdfTextExtractor;
use RuntimeException;

final class ThrowingPdfTextExtractor implements PdfTextExtractor
{
    public function __construct(private readonly string $message = 'boom') {}

    public function extract(string $path): string
    {
        throw new RuntimeException($this->message);
    }
}
