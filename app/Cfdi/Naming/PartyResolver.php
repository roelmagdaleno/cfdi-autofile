<?php

namespace App\Cfdi\Naming;

use App\Cfdi\Data\CfdiData;
use App\Cfdi\Data\CounterpartyRef;
use App\Cfdi\Data\TypeLetter;

final class PartyResolver
{
    /**
     * I = ingreso (yo emiti), G = gasto (yo recibi), ? = no se pudo determinar.
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
     * Solo se compara el EMISOR: si soy el emisor, la contraparte es el receptor;
     * en cualquier otro caso (incluido `?`) la contraparte es el emisor.
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
