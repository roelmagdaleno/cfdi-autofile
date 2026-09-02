<?php

namespace App\Cfdi\Parsing;

interface PdfTextExtractor
{
    /**
     * Texto plano del PDF. Puede devolver cadena vacia si no hay texto.
     */
    public function extract(string $path): string;
}
