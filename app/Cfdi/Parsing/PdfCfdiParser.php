<?php

namespace App\Cfdi\Parsing;

use App\Cfdi\Data\CfdiData;
use App\Cfdi\Data\Confidence;
use App\Cfdi\Data\SourceFormat;
use App\Cfdi\Exceptions\CfdiRejectedException;
use App\Cfdi\Support\Rfc;
use Throwable;

/**
 * Fallback when there is no XML: low confidence.
 *
 * It never invents data: whatever cannot be extracted stays `null`.
 */
final class PdfCfdiParser
{
    public const string UUID_PATTERN = '/\b[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\b/u';

    /**
     * The `(?<!sub)` keeps "Subtotal: $2,500.00" from being read as the total.
     */
    public const string TOTAL_PATTERN = '/(?<!sub)(?<![a-z])(?:gran\s+total|importe\s+total|total)\b\D{0,20}?\$?\s*(\d{1,3}(?:,\d{3})*(?:\.\d{2})?|\d+\.\d{2})/iu';

    public const string DATE_ISO_PATTERN = '/\b(\d{4}-\d{2}-\d{2})(?:[T ]\d{2}:\d{2}:\d{2})?\b/u';

    public const string DATE_DMY_PATTERN = '/\b(\d{2})\/(\d{2})\/(\d{4})\b/u';

    public function __construct(private readonly PdfTextExtractor $extractor) {}

    /**
     * Warning attached to every CFDI read out of a PDF by heuristics.
     */
    public static function heuristicWarning(): string
    {
        return (string) __('cfdi.warning.pdf_heuristic');
    }

    /**
     * @throws CfdiRejectedException
     */
    public function parse(string $path): CfdiData
    {
        $text = $this->extractText($path);

        // 1. Extractable text. `\S` is used instead of trim(): PHP's trim() does
        // not strip the non-breaking space (U+00A0), but Python's str.strip() does.
        if (preg_match('/\S/u', $text) !== 1) {
            throw new CfdiRejectedException((string) __('cfdi.rejected.pdf_no_text'));
        }

        $upper = mb_strtoupper($text);

        // 2. At least 2 well-formed RFCs. The RFC regex runs over the UPPERCASED
        // text; the others run over the original.
        preg_match_all(Rfc::PATTERN, $upper, $matches);
        /** @var list<string> $rfcs */
        $rfcs = array_values(array_unique($matches[1]));

        if (count($rfcs) < 2) {
            throw new CfdiRejectedException(
                (string) __('cfdi.rejected.pdf_not_enough_rfcs', ['count' => count($rfcs)]),
            );
        }

        // 3. Stamped: either a UUID, or the phrase "folio fiscal" as a substring.
        // That phrase is the SAT's own wording and stays in Spanish.
        $hasUuid = preg_match(self::UUID_PATTERN, $text, $uuidMatch) === 1;

        if (! $hasUuid && ! str_contains($upper, 'FOLIO FISCAL')) {
            throw new CfdiRejectedException((string) __('cfdi.rejected.pdf_not_stamped'));
        }

        // 4. Total: the LAST match is taken, not the first. The total usually
        // comes at the end of the document.
        preg_match_all(self::TOTAL_PATTERN, $text, $totals, PREG_SET_ORDER);

        if ($totals === []) {
            throw new CfdiRejectedException((string) __('cfdi.rejected.pdf_no_total'));
        }

        $total = str_replace(',', '', $totals[count($totals) - 1][1]);

        // 5. Date: ISO first, then dd/mm/yyyy.
        $date = null;

        if (preg_match(self::DATE_ISO_PATTERN, $text, $iso) === 1) {
            $date = $iso[1];
        } elseif (preg_match(self::DATE_DMY_PATTERN, $text, $dmy) === 1) {
            $date = "{$dmy[3]}-{$dmy[2]}-{$dmy[1]}";
        }

        if ($date === null) {
            throw new CfdiRejectedException((string) __('cfdi.rejected.pdf_no_date'));
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
            warnings: [self::heuristicWarning()],
        );
    }

    /**
     * Any library failure is a REJECTION, not a crash.
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
            throw new CfdiRejectedException(
                (string) __('cfdi.rejected.pdf_unreadable', ['message' => $e->getMessage()]),
            );
        }
    }
}
