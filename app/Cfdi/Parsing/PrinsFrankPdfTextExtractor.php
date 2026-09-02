<?php

namespace App\Cfdi\Parsing;

use App\Cfdi\Exceptions\CfdiRejectedException;
use PrinsFrank\PdfParser\PdfParser;

final class PrinsFrankPdfTextExtractor implements PdfTextExtractor
{
    public function extract(string $path): string
    {
        if (! class_exists(PdfParser::class)) {
            throw new CfdiRejectedException((string) __('cfdi.rejected.pdf_parser_missing'));
        }

        // The "\n" separator mirrors the original's `"\n".join(pages)`.
        return (new PdfParser)->parseFile($path)->getText("\n");
    }
}
