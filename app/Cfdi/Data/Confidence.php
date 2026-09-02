<?php

namespace App\Cfdi\Data;

enum Confidence: string
{
    /** Datos leidos del XML: fuente de verdad. */
    case High = 'alta';

    /** Datos leidos del PDF por heuristica. */
    case Low = 'baja';
}
