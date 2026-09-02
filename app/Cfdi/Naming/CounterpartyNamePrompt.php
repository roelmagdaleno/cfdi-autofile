<?php

namespace App\Cfdi\Naming;

/**
 * Seam de interaccion: es lo que mantiene al orquestador libre de terminal y de
 * HTTP. No existe ninguna bandera `interactivo` en el nucleo.
 */
interface CounterpartyNamePrompt
{
    /**
     * Nombre a usar para la contraparte.
     *
     * Las implementaciones nunca deben lanzar excepciones, y deben caer en
     * `$suggestion` si el usuario no contesta nada.
     */
    public function askName(string $rfc, ?string $legalName, string $suggestion): string;
}
