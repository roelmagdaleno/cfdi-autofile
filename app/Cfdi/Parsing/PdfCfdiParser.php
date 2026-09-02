<?php

namespace App\Cfdi\Parsing;

use App\Cfdi\Data\CfdiData;
use App\Cfdi\Data\Confidence;
use App\Cfdi\Data\SourceFormat;
use App\Cfdi\Exceptions\CfdiRejectedException;
use App\Cfdi\Support\Rfc;
use Throwable;

/**
 * Fallback cuando no hay XML: confianza baja.
 *
 * Nunca inventa datos: lo que no se extrae queda en `null`.
 */
final class PdfCfdiParser
{
    public const string UUID_PATTERN = '/\b[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\b/u';

    /**
     * El `(?<!sub)` evita que "Subtotal: $2,500.00" se confunda con el total.
     */
    public const string TOTAL_PATTERN = '/(?<!sub)(?<![a-z])(?:gran\s+total|importe\s+total|total)\b\D{0,20}?\$?\s*(\d{1,3}(?:,\d{3})*(?:\.\d{2})?|\d+\.\d{2})/iu';

    public const string DATE_ISO_PATTERN = '/\b(\d{4}-\d{2}-\d{2})(?:[T ]\d{2}:\d{2}:\d{2})?\b/u';

    public const string DATE_DMY_PATTERN = '/\b(\d{2})\/(\d{2})\/(\d{4})\b/u';

    public const string HEURISTIC_WARNING = 'datos extraidos del PDF por heuristica; el orden emisor/receptor puede estar invertido y no se pudo determinar el tipo de comprobante';

    public function __construct(private readonly PdfTextExtractor $extractor) {}

    /**
     * @throws CfdiRejectedException
     */
    public function parse(string $path): CfdiData
    {
        $text = $this->extractText($path);

        // 1. Texto extraible. Se usa `\S` en vez de trim(): trim() de PHP no
        // quita el espacio duro (U+00A0), pero str.strip() de Python si.
        if (preg_match('/\S/u', $text) !== 1) {
            throw new CfdiRejectedException(
                'Rechazado: el PDF no tiene texto extraible (probablemente es un escaneo '
                .'o imagen). Consigue el XML o un PDF con texto.'
            );
        }

        $upper = mb_strtoupper($text);

        // 2. Al menos 2 RFCs con formato valido. El regex de RFC corre sobre el
        // texto en MAYUSCULAS; los demas sobre el texto original.
        preg_match_all(Rfc::PATTERN, $upper, $matches);
        /** @var list<string> $rfcs */
        $rfcs = array_values(array_unique($matches[1]));

        if (count($rfcs) < 2) {
            throw new CfdiRejectedException(sprintf(
                'Rechazado: se encontraron %d RFC(s) con formato valido y un CFDI '
                .'necesita al menos 2 (emisor y receptor).',
                count($rfcs),
            ));
        }

        // 3. Timbrado: un UUID, o la frase "folio fiscal" como subcadena.
        $hasUuid = preg_match(self::UUID_PATTERN, $text, $uuidMatch) === 1;

        if (! $hasUuid && ! str_contains($upper, 'FOLIO FISCAL')) {
            throw new CfdiRejectedException(
                "Rechazado: no aparece un UUID ni la frase 'folio fiscal'; el documento no "
                .'parece estar timbrado ante el SAT.'
            );
        }

        // 4. Total: se toma la ULTIMA coincidencia, no la primera. El total
        // suele venir al final del documento.
        preg_match_all(self::TOTAL_PATTERN, $text, $totals, PREG_SET_ORDER);

        if ($totals === []) {
            throw new CfdiRejectedException('Rechazado: no se pudo identificar un total en el texto del PDF.');
        }

        $total = str_replace(',', '', $totals[count($totals) - 1][1]);

        // 5. Fecha: primero ISO, luego dd/mm/yyyy.
        $date = null;

        if (preg_match(self::DATE_ISO_PATTERN, $text, $iso) === 1) {
            $date = $iso[1];
        } elseif (preg_match(self::DATE_DMY_PATTERN, $text, $dmy) === 1) {
            $date = "{$dmy[3]}-{$dmy[2]}-{$dmy[1]}";
        }

        if ($date === null) {
            throw new CfdiRejectedException('Rechazado: no se pudo identificar la fecha de emision en el texto del PDF.');
        }

        return new CfdiData(
            date: $date,
            total: $total,
            invoiceType: null,
            issuerRfc: $rfcs[0],
            recipientRfc: $rfcs[1],
            uuid: $hasUuid ? mb_strtolower($uuidMatch[0]) : null,
            confidence: Confidence::Low,
            source: SourceFormat::Pdf,
            warnings: [self::HEURISTIC_WARNING],
        );
    }

    /**
     * Cualquier fallo de la libreria es un RECHAZO, no un crash.
     *
     * @throws CfdiRejectedException
     */
    private function extractText(string $path): string
    {
        try {
            return $this->extractor->extract($path);
        } catch (CfdiRejectedException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new CfdiRejectedException("No se pudo abrir el PDF: {$e->getMessage()}");
        }
    }
}
