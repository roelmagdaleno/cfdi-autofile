<?php

namespace App\Cfdi\Support;

final class Rfc
{
    /**
     * Mexican RFC: 3 letters (company) or 4 (individual) + 6 date digits + 3 of
     * the homoclave. It is the SAT's format and stays exactly as it is.
     *
     * The `/u` modifier is mandatory: without it, `[A-ZÑ&]` is a byte class and
     * an RFC starting with `Ñ` does not match.
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
