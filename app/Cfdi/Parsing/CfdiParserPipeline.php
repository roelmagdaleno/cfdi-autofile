<?php

namespace App\Cfdi\Parsing;

use App\Cfdi\Data\CfdiData;
use App\Cfdi\Data\FileGroup;
use App\Cfdi\Exceptions\CfdiRejectedException;

/**
 * XML primero, PDF como fallback.
 *
 * Vive aparte del orquestador porque la web tambien lo necesita: la fase de
 * analisis debe decidir a quien preguntarle sin escribir nada, y no puede
 * reimplementar esta regla sin que se desincronice.
 */
final class CfdiParserPipeline
{
    public function __construct(
        private readonly XmlCfdiParser $xmlParser,
        private readonly PdfCfdiParser $pdfParser,
    ) {}

    /**
     * @throws CfdiRejectedException con el primer motivo si ninguno funciono
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
                // Si el PDF funciona, el motivo del XML se descarta.
                return $this->pdfParser->parse($group->pdf);
            } catch (CfdiRejectedException $e) {
                // Si no, se conserva el PRIMER motivo (el del XML si lo hubo).
                $reason ??= $e->getMessage();
            }
        }

        throw new CfdiRejectedException($reason ?? 'Rechazado: el grupo no tiene archivos que procesar.');
    }
}
