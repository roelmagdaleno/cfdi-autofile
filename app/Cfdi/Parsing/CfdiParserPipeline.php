<?php

namespace App\Cfdi\Parsing;

use App\Cfdi\Data\CfdiData;
use App\Cfdi\Data\FileGroup;
use App\Cfdi\Exceptions\CfdiRejectedException;

/**
 * XML first, PDF as a fallback.
 *
 * It lives apart from the orchestrator because the web needs it too: the
 * analysis phase has to decide who to ask about without writing anything, and
 * it cannot reimplement this rule without the two drifting apart.
 */
final class CfdiParserPipeline
{
    public function __construct(
        private readonly XmlCfdiParser $xmlParser,
        private readonly PdfCfdiParser $pdfParser,
    ) {}

    /**
     * @throws CfdiRejectedException with the first reason if none worked
     */
    public function parse(FileGroup $group): CfdiData
    {
        $reason = null;

        if ($group->xml !== null) {
            try {
                return $this->xmlParser->parse($group->xml);
            } catch (CfdiRejectedException $e) {
                $reason = $e->getMessage();
            }
        }

        if ($group->pdf !== null) {
            try {
                // If the PDF works, the XML's reason is discarded.
                return $this->pdfParser->parse($group->pdf);
            } catch (CfdiRejectedException $e) {
                // Otherwise the FIRST reason is kept (the XML's, if there was one).
                $reason ??= $e->getMessage();
            }
        }

        throw new CfdiRejectedException($reason ?? (string) __('cfdi.rejected.empty_group'));
    }
}
