<?php

namespace App\Cfdi\Parsing;

interface PdfTextExtractor
{
    /**
     * Plain text of the PDF. It may return an empty string when there is no text.
     */
    public function extract(string $path): string;
}
