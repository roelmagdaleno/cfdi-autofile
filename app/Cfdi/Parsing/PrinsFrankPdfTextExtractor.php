<?php

namespace App\Cfdi\Parsing;

use App\Cfdi\Exceptions\CfdiRejectedException;
use PrinsFrank\PdfParser\PdfParser;

final class PrinsFrankPdfTextExtractor implements PdfTextExtractor
{
    public function extract(string $path): string
    {
        if (! class_exists(PdfParser::class)) {
            throw new CfdiRejectedException('No se pudo leer el PDF: falta la dependencia prinsfrank/pdfparser.');
        }

        // El separador "\n" replica el `"\n".join(paginas)` del original.
        return (new PdfParser)->parseFile($path)->getText("\n");
    }
}
