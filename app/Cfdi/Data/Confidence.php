<?php

namespace App\Cfdi\Data;

enum Confidence: string
{
    /** Data read from the XML: the source of truth. */
    case High = 'high';

    /** Data read from the PDF by heuristics. */
    case Low = 'low';

    public function label(): string
    {
        return (string) __("cfdi.confidence.{$this->value}");
    }
}
