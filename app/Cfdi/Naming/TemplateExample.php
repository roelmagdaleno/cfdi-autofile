<?php

namespace App\Cfdi\Naming;

use App\Cfdi\Data\CfdiData;
use App\Cfdi\Data\TypeLetter;

/**
 * The example name used to illustrate a template.
 *
 * The sample data lives here so that Settings and Process show exactly the same
 * example.
 */
final class TemplateExample
{
    public const string COUNTERPARTY = 'SERVICIOS CONTABLES NORTE';

    public static function name(string $template, TypeLetter $type): string
    {
        $data = new CfdiData(
            date: '2026-02-14T11:32:05',
            total: '11600.00',
            invoiceType: 'I',
            uuid: 'A1B2C3D4-E5F6-4789-ABCD-1234567890AB',
        );

        return app(FileNameBuilder::class)->build(
            $data,
            $type,
            self::COUNTERPARTY,
            $template,
            '.xml',
        );
    }
}
