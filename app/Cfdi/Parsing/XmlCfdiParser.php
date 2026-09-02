<?php

namespace App\Cfdi\Parsing;

use App\Cfdi\Data\CfdiData;
use App\Cfdi\Data\Confidence;
use App\Cfdi\Data\SourceFormat;
use App\Cfdi\Exceptions\CfdiRejectedException;
use DOMDocument;
use DOMElement;

/**
 * The XML is the source of truth: high confidence.
 *
 * Node lookups go by local name + namespace, never by prefix: it does not
 * matter whether the document uses `cfdi:`, `c:` or no prefix at all.
 *
 * The node and attribute names below stay in Spanish on purpose: they are the
 * SAT's CFDI schema, not app vocabulary, and renaming them would break parsing.
 */
final class XmlCfdiParser
{
    public const string NS_CFDI_3 = 'http://www.sat.gob.mx/cfd/3';

    public const string NS_CFDI_4 = 'http://www.sat.gob.mx/cfd/4';

    public const string NS_TFD = 'http://www.sat.gob.mx/TimbreFiscalDigital';

    /**
     * @throws CfdiRejectedException
     */
    public function parse(string $path): CfdiData
    {
        $root = $this->load($path);

        if ($root->localName !== 'Comprobante'
            || ! in_array($root->namespaceURI ?? '', [self::NS_CFDI_3, self::NS_CFDI_4], true)) {
            throw new CfdiRejectedException((string) __('cfdi.rejected.not_a_cfdi'));
        }

        $date = $this->attribute($root, 'Fecha');
        $total = $this->attribute($root, 'Total');
        $invoiceType = $this->attribute($root, 'TipoDeComprobante');

        $issuer = $this->directChild($root, 'Emisor');
        $issuerRfc = $issuer === null ? null : $this->attribute($issuer, 'Rfc');
        $issuerName = $issuer === null ? null : $this->attribute($issuer, 'Nombre');

        $recipient = $this->directChild($root, 'Receptor');
        $recipientRfc = $recipient === null ? null : $this->attribute($recipient, 'Rfc');
        $recipientName = $recipient === null ? null : $this->attribute($recipient, 'Nombre');

        $uuid = null;
        $complement = $this->directChild($root, 'Complemento');

        if ($complement !== null) {
            $stamp = $complement->getElementsByTagNameNS(self::NS_TFD, 'TimbreFiscalDigital')->item(0);

            if ($stamp instanceof DOMElement) {
                // Exactly as it comes, without changing the case.
                $uuid = $this->attribute($stamp, 'UUID');
            }
        }

        $replacedUuid = null;
        $warnings = [];
        $related = $this->directChild($root, 'CfdiRelacionados');

        if ($related !== null && $this->attribute($related, 'TipoRelacion') === '04') {
            $child = $this->directChild($related, 'CfdiRelacionado');
            $relatedUuid = $child === null ? null : $this->attribute($child, 'UUID');

            if ($relatedUuid !== null && $relatedUuid !== '') {
                $replacedUuid = $relatedUuid;
                $warnings[] = (string) __('cfdi.warning.replaces', ['uuid' => $replacedUuid]);
            }
        }

        // Rejections for missing data go last, in this exact order.
        if ($date === null || $date === '') {
            throw new CfdiRejectedException((string) __('cfdi.rejected.missing_date'));
        }

        if ($total === null || $total === '') {
            throw new CfdiRejectedException((string) __('cfdi.rejected.missing_total'));
        }

        if ($issuerRfc === null || $issuerRfc === '') {
            throw new CfdiRejectedException((string) __('cfdi.rejected.missing_issuer_rfc'));
        }

        if ($recipientRfc === null || $recipientRfc === '') {
            throw new CfdiRejectedException((string) __('cfdi.rejected.missing_recipient_rfc'));
        }

        return new CfdiData(
            date: $date,
            total: $total,
            invoiceType: $invoiceType,
            issuerRfc: $issuerRfc,
            issuerName: $issuerName,
            recipientRfc: $recipientRfc,
            recipientName: $recipientName,
            uuid: $uuid,
            replacedUuid: $replacedUuid,
            confidence: Confidence::High,
            source: SourceFormat::Xml,
            warnings: $warnings,
        );
    }

    /**
     * @throws CfdiRejectedException
     */
    private function load(string $path): DOMElement
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new CfdiRejectedException((string) __('cfdi.rejected.unreadable_xml'));
        }

        $previous = libxml_use_internal_errors(true);

        try {
            libxml_clear_errors();

            $document = new DOMDocument;
            $loaded = $document->loadXML($contents, LIBXML_NONET);
            $root = $document->documentElement;

            if ($loaded === false || $root === null) {
                $error = libxml_get_last_error();
                $detail = $error === false
                    ? (string) __('cfdi.rejected.unknown_error')
                    : sprintf('%s: line %d, column %d', rtrim($error->message), $error->line, $error->column);

                throw new CfdiRejectedException((string) __('cfdi.rejected.malformed_xml', ['detail' => $detail]));
            }

            return $root;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function directChild(DOMElement $parent, string $localName): ?DOMElement
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }

    /**
     * `getAttribute()` returns `''` for a missing attribute; here we tell
     * missing (`null`) apart from empty.
     */
    private function attribute(DOMElement $element, string $name): ?string
    {
        return $element->hasAttribute($name) ? $element->getAttribute($name) : null;
    }
}
