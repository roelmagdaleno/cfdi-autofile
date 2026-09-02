<?php

namespace App\Cfdi\Parsing;

use App\Cfdi\Data\CfdiData;
use App\Cfdi\Data\Confidence;
use App\Cfdi\Data\SourceFormat;
use App\Cfdi\Exceptions\CfdiRejectedException;
use DOMDocument;
use DOMElement;

/**
 * El XML es la fuente de verdad: confianza alta.
 *
 * La busqueda de nodos es por nombre local + namespace, nunca por prefijo: da
 * igual si el documento usa `cfdi:`, `c:` o ningun prefijo.
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
            throw new CfdiRejectedException(
                'Este XML no es un CFDI valido: el nodo raiz no es cfdi:Comprobante '
                .'en el namespace de CFDI 3.3 o 4.0.'
            );
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
                // Tal cual viene, sin cambiar mayusculas.
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
                $warnings[] = "sustituye al CFDI {$replacedUuid} (TipoRelacion 04)";
            }
        }

        // Los rechazos por datos faltantes van al final, en este orden exacto.
        if ($date === null || $date === '') {
            throw new CfdiRejectedException('CFDI incompleto: falta el atributo Fecha en el Comprobante.');
        }

        if ($total === null || $total === '') {
            throw new CfdiRejectedException('CFDI incompleto: falta el atributo Total en el Comprobante.');
        }

        if ($issuerRfc === null || $issuerRfc === '') {
            throw new CfdiRejectedException('CFDI incompleto: falta el RFC del Emisor.');
        }

        if ($recipientRfc === null || $recipientRfc === '') {
            throw new CfdiRejectedException('CFDI incompleto: falta el RFC del Receptor.');
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
            throw new CfdiRejectedException('Este XML no es un CFDI valido: XML mal formado (no se pudo leer el archivo).');
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
                    ? 'error desconocido'
                    : sprintf('%s: line %d, column %d', rtrim($error->message), $error->line, $error->column);

                throw new CfdiRejectedException("Este XML no es un CFDI valido: XML mal formado ({$detail}).");
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
     * `getAttribute()` devuelve `''` para un atributo ausente; aqui distinguimos
     * ausente (`null`) de vacio.
     */
    private function attribute(DOMElement $element, string $name): ?string
    {
        return $element->hasAttribute($name) ? $element->getAttribute($name) : null;
    }
}
