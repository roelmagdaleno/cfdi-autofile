<?php

namespace App\Cfdi\Data;

/**
 * Data extracted from a CFDI. Anything that could not be extracted with
 * certainty stays `null`: a value is never invented.
 */
final class CfdiData
{
    /**
     * @param  list<string>  $warnings
     */
    public function __construct(
        public readonly ?string $date = null,
        public readonly ?string $total = null,
        public readonly ?string $invoiceType = null,
        public readonly ?string $issuerRfc = null,
        public readonly ?string $issuerName = null,
        public readonly ?string $recipientRfc = null,
        public readonly ?string $recipientName = null,
        public readonly ?string $uuid = null,
        public readonly ?string $replacedUuid = null,
        public readonly Confidence $confidence = Confidence::High,
        public readonly ?SourceFormat $source = null,
        public readonly array $warnings = [],
    ) {}

    public function isCreditNote(): bool
    {
        return $this->invoiceType === 'E';
    }

    /**
     * A copy with one more warning appended.
     */
    public function withWarning(string $warning): self
    {
        return new self(
            date: $this->date,
            total: $this->total,
            invoiceType: $this->invoiceType,
            issuerRfc: $this->issuerRfc,
            issuerName: $this->issuerName,
            recipientRfc: $this->recipientRfc,
            recipientName: $this->recipientName,
            uuid: $this->uuid,
            replacedUuid: $this->replacedUuid,
            confidence: $this->confidence,
            source: $this->source,
            warnings: [...$this->warnings, $warning],
        );
    }
}
