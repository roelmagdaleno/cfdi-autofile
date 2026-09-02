<?php

return [
    /*
     * Cadenas que llegan al disco. Terminan en nombres de archivo y de carpeta
     * en la maquina del usuario, asi que cambiar el idioma cambia lo que se
     * escribe.
     */
    'credit_note_suffix' => ' (nota de credito)',
    'default_output_directory' => 'Renombradas',
    'generic_counterparty' => 'General',
    'zip_filename' => 'cfdis-renombrados.zip',

    'status' => [
        'ok' => 'OK',
        'review' => 'REVISAR',
        'rejected' => 'RECHAZADO',
    ],

    'confidence' => [
        'high' => 'alta',
        'low' => 'baja',
    ],

    'action' => [
        'copied' => 'copiado',
        'moved' => 'movido',
    ],

    /*
     * Etiquetas de las pastillas. A diferencia de `credit_note_suffix`, estas
     * nunca llegan al disco: solo nombran el placeholder en pantalla, asi que
     * si llevan acentos.
     */
    'placeholder_label' => [
        'date' => 'fecha',
        'type' => 'tipo',
        'counterparty' => 'contraparte',
        'amount' => 'monto',
        'uuid' => 'uuid',
        'credit_note' => 'nota de crédito',
    ],

    'placeholder' => [
        'date' => 'Fecha de emision en formato YYYY-MM-DD.',
        'type' => ':income si eres el emisor, :expense si eres el receptor.',
        'counterparty' => 'Nombre corto de la otra parte.',
        'amount' => 'Total con dos decimales.',
        'uuid' => 'Folio fiscal del timbre.',
        'credit_note' => 'El texto "(nota de credito)" si aplica, o nada.',
    ],

    'rejected' => [
        'not_a_cfdi' => 'Este XML no es un CFDI valido: el nodo raiz no es cfdi:Comprobante en el namespace de CFDI 3.3 o 4.0.',
        'unreadable_xml' => 'Este XML no es un CFDI valido: XML mal formado (no se pudo leer el archivo).',
        'malformed_xml' => 'Este XML no es un CFDI valido: XML mal formado (:detail).',
        'unknown_error' => 'error desconocido',
        'missing_date' => 'CFDI incompleto: falta el atributo Fecha en el Comprobante.',
        'missing_total' => 'CFDI incompleto: falta el atributo Total en el Comprobante.',
        'missing_issuer_rfc' => 'CFDI incompleto: falta el RFC del Emisor.',
        'missing_recipient_rfc' => 'CFDI incompleto: falta el RFC del Receptor.',
        'pdf_no_text' => 'Rechazado: el PDF no tiene texto extraible (probablemente es un escaneo o imagen). Consigue el XML o un PDF con texto.',
        'pdf_not_enough_rfcs' => 'Rechazado: se encontraron :count RFC(s) con formato valido y un CFDI necesita al menos 2 (emisor y receptor).',
        'pdf_not_stamped' => "Rechazado: no aparece un UUID ni la frase 'folio fiscal'; el documento no parece estar timbrado ante el SAT.",
        'pdf_no_total' => 'Rechazado: no se pudo identificar un total en el texto del PDF.',
        'pdf_no_date' => 'Rechazado: no se pudo identificar la fecha de emision en el texto del PDF.',
        'pdf_unreadable' => 'No se pudo abrir el PDF: :message',
        'pdf_parser_missing' => 'No se pudo leer el PDF: falta la dependencia prinsfrank/pdfparser.',
        'empty_group' => 'Rechazado: el grupo no tiene archivos que procesar.',
    ],

    'collection' => [
        'missing' => 'no existe.',
        'is_directory' => 'es una carpeta (usa --dir).',
        'unsupported_extension' => 'extension no soportada (solo .pdf y .xml).',
        'not_a_directory' => 'no es una carpeta.',
    ],

    'warning' => [
        'replaces' => 'sustituye al CFDI :uuid (TipoRelacion 04)',
        'pdf_heuristic' => 'datos extraidos del PDF por heuristica; el orden emisor/receptor puede estar invertido y no se pudo determinar el tipo de comprobante',
        'rfc_not_a_party' => 'tu RFC :rfc no aparece como emisor ni receptor',
    ],

    /*
     * Razones que terminan en la columna «Drive» de la tabla de resultados y en
     * las advertencias de la corrida. No llegan al disco, así que llevan
     * acentos.
     */
    'drive' => [
        'not_connected' => 'No hay una cuenta de Google conectada. Conéctala en Ajustes.',
        'no_refresh_token' => 'Google no devolvió un refresh token. Revoca el acceso de la app en tu cuenta de Google y vuelve a conectarla.',
        'request_failed' => 'Google Drive respondió :status en :operation. :detail',

        'skipped' => [
            'no_date' => 'No se subió: el CFDI no tiene fecha de emisión, así que no hay año de carpeta.',
            'unknown_type' => 'No se subió: tu RFC no aparece en el CFDI, así que no es ni emitida ni recibida.',
            'missing_file' => 'No se subió: el archivo ya no está en su ubicación.',
        ],

        'warning' => [
            'some_failed' => ':count archivo(s) no se pudieron subir a Google Drive. Puedes reintentarlo desde la tabla.',
        ],
    ],

    'error' => [
        'invalid_file_name' => 'Nombre de archivo invalido.',
        'run_produced_no_files' => 'La corrida no produjo archivos.',
        'zip_failed' => 'No se pudo crear el ZIP.',
        'unreadable_file' => 'No se pudo leer :path.',
        'run_not_finished' => 'La corrida aún no ha terminado.',
        'run_files_cleaned' => 'Los archivos de esta corrida ya se limpiaron.',
        'file_moved' => 'El archivo ya no está en su ubicación.',
    ],
];
