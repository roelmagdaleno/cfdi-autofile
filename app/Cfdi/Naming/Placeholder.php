<?php

namespace App\Cfdi\Naming;

/**
 * Catalogo de placeholders de la plantilla del nombre.
 *
 * Es la unica fuente de verdad: de aqui salen las claves que sustituye
 * `FileNameBuilder`, la regla de validacion de la plantilla y las pills que
 * dibuja la pantalla de ajustes. Los valores son en espanol a proposito.
 */
enum Placeholder: string
{
    case Fecha = 'fecha';

    case Tipo = 'tipo';

    case Contraparte = 'contraparte';

    case Monto = 'monto';

    case Uuid = 'uuid';

    case NotaCredito = 'nota_credito';

    /** El placeholder tal como se escribe en la plantilla. */
    public function token(): string
    {
        return '{'.$this->value.'}';
    }

    public function description(): string
    {
        return match ($this) {
            self::Fecha => 'Fecha de emision en formato YYYY-MM-DD.',
            self::Tipo => 'I si eres el emisor, G si eres el receptor.',
            self::Contraparte => 'Nombre corto de la otra parte.',
            self::Monto => 'Total con dos decimales.',
            self::Uuid => 'Folio fiscal del timbre.',
            self::NotaCredito => 'El texto "(nota de credito)" si aplica, o nada.',
        };
    }

    /**
     * Parte la plantilla en texto literal y placeholders conocidos.
     *
     * Un `{placeholder}` que no este en el catalogo se devuelve como texto, tal
     * como lo trata `FileNameBuilder`.
     *
     * @return list<array{placeholder: self|null, text: string}>
     */
    public static function segments(string $template): array
    {
        $pieces = preg_split(self::pattern(), $template, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($pieces === false) {
            return [['placeholder' => null, 'text' => $template]];
        }

        $segments = [];

        // `PREG_SPLIT_DELIM_CAPTURE` intercala el grupo capturado (el nombre del
        // placeholder, sin llaves) entre los trozos de texto, asi que los indices
        // impares son siempre placeholders. Mirar la posicion y no el valor evita
        // confundir un texto que se llame igual, como la plantilla `fecha`.
        foreach ($pieces as $index => $piece) {
            $placeholder = $index % 2 === 1 ? self::from($piece) : null;

            if ($placeholder instanceof self) {
                $segments[] = ['placeholder' => $placeholder, 'text' => $placeholder->token()];

                continue;
            }

            if ($piece !== '') {
                $segments[] = ['placeholder' => null, 'text' => $piece];
            }
        }

        return $segments;
    }

    /**
     * Patron que detecta cualquier placeholder conocido dentro de una plantilla.
     */
    public static function pattern(): string
    {
        $names = array_map(
            static fn (self $placeholder): string => preg_quote($placeholder->value, '/'),
            self::cases(),
        );

        return '/\{('.implode('|', $names).')\}/u';
    }
}
