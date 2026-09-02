<?php

namespace App\Cfdi\Support;

final class Rfc
{
    /**
     * RFC mexicano: 3 letras (moral) o 4 (fisica) + 6 digitos de fecha + 3 de homoclave.
     *
     * El modificador `/u` es obligatorio: sin el, `[A-ZÑ&]` es una clase de bytes
     * y un RFC que empieza con `Ñ` no hace match.
     */
    public const string PATTERN = '/\b([A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3})\b/u';

    /** @var list<string> */
    public const array GENERIC = ['XAXX010101000', 'XEXX010101000'];

    public static function normalize(?string $rfc): string
    {
        return mb_strtoupper(trim($rfc ?? ''));
    }

    public static function isGeneric(string $rfc): bool
    {
        return in_array($rfc, self::GENERIC, true);
    }
}
