<?php

namespace App\Cfdi\Naming;

use App\Cfdi\Data\CfdiData;
use App\Cfdi\Data\CounterpartyRef;
use App\Cfdi\Data\TypeLetter;

final class PartyResolver
{
    /**
     * I = income (I issued it), G = expense (I received it), ? = undetermined.
     */
    public static function typeLetter(CfdiData $data, string $myRfc): TypeLetter
    {
        $myRfc = mb_strtoupper($myRfc);

        if ($data->issuerRfc !== null && $data->issuerRfc !== '' && mb_strtoupper($data->issuerRfc) === $myRfc) {
            return TypeLetter::Income;
        }

        if ($data->recipientRfc !== null && $data->recipientRfc !== '' && mb_strtoupper($data->recipientRfc) === $myRfc) {
            return TypeLetter::Expense;
        }

        return TypeLetter::Unknown;
    }

    /**
     * Only the ISSUER is compared: if I am the issuer, the counterparty is the
     * recipient; in every other case (including `?`) it is the issuer.
     */
    public static function counterpartyOf(CfdiData $data, string $myRfc): CounterpartyRef
    {
        $myRfc = mb_strtoupper($myRfc);

        if ($data->issuerRfc !== null && $data->issuerRfc !== '' && mb_strtoupper($data->issuerRfc) === $myRfc) {
            return new CounterpartyRef($data->recipientRfc, $data->recipientName);
        }

        return new CounterpartyRef($data->issuerRfc, $data->issuerName);
    }
}
