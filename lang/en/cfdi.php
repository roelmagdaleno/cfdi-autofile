<?php

return [
    /*
     * Disk-facing strings. These end up in file and directory names on the
     * user's machine, so a locale change also changes what gets written.
     */
    'credit_note_suffix' => ' (credit note)',
    'default_output_directory' => 'Renamed',
    'generic_counterparty' => 'General',
    'zip_filename' => 'renamed-cfdis.zip',

    'status' => [
        'ok' => 'OK',
        'review' => 'REVIEW',
        'rejected' => 'REJECTED',
    ],

    'confidence' => [
        'high' => 'high',
        'low' => 'low',
    ],

    'action' => [
        'copied' => 'copied',
        'moved' => 'moved',
    ],

    /*
     * Pill labels. Unlike `credit_note_suffix`, these never reach the
     * filesystem: they only name the placeholder on screen.
     */
    'placeholder_label' => [
        'date' => 'date',
        'type' => 'type',
        'counterparty' => 'counterparty',
        'amount' => 'amount',
        'uuid' => 'uuid',
        'credit_note' => 'credit note',
    ],

    'placeholder' => [
        'date' => 'Issue date in YYYY-MM-DD format.',
        'type' => ':income if you are the issuer, :expense if you are the recipient.',
        'counterparty' => 'Short name of the other party.',
        'amount' => 'Total with two decimals.',
        'uuid' => 'Tax folio of the stamp.',
        'credit_note' => 'The text "(credit note)" when it applies, or nothing.',
    ],

    'rejected' => [
        'not_a_cfdi' => 'This XML is not a valid CFDI: the root node is not cfdi:Comprobante in the CFDI 3.3 or 4.0 namespace.',
        'unreadable_xml' => 'This XML is not a valid CFDI: malformed XML (the file could not be read).',
        'malformed_xml' => 'This XML is not a valid CFDI: malformed XML (:detail).',
        'unknown_error' => 'unknown error',
        'missing_date' => 'Incomplete CFDI: the Fecha attribute is missing from the Comprobante.',
        'missing_total' => 'Incomplete CFDI: the Total attribute is missing from the Comprobante.',
        'missing_issuer_rfc' => 'Incomplete CFDI: the Emisor RFC is missing.',
        'missing_recipient_rfc' => 'Incomplete CFDI: the Receptor RFC is missing.',
        'pdf_no_text' => 'Rejected: the PDF has no extractable text (it is probably a scan or an image). Get the XML or a PDF with text.',
        'pdf_not_enough_rfcs' => 'Rejected: found :count RFC(s) with a valid format and a CFDI needs at least 2 (issuer and recipient).',
        'pdf_not_stamped' => "Rejected: no UUID and no 'folio fiscal' phrase appear; the document does not look stamped by the SAT.",
        'pdf_no_total' => 'Rejected: could not identify a total in the PDF text.',
        'pdf_no_date' => 'Rejected: could not identify the issue date in the PDF text.',
        'pdf_unreadable' => 'Could not open the PDF: :message',
        'pdf_parser_missing' => 'Could not read the PDF: the prinsfrank/pdfparser dependency is missing.',
        'empty_group' => 'Rejected: the group has no files to process.',
    ],

    'collection' => [
        'missing' => 'does not exist.',
        'is_directory' => 'is a folder (use --dir).',
        'unsupported_extension' => 'unsupported extension (only .pdf and .xml).',
        'not_a_directory' => 'is not a folder.',
    ],

    'warning' => [
        'replaces' => 'replaces CFDI :uuid (TipoRelacion 04)',
        'pdf_heuristic' => 'data extracted from the PDF by heuristics; the issuer/recipient order may be swapped and the invoice type could not be determined',
        'rfc_not_a_party' => 'your RFC :rfc does not appear as issuer or recipient',
    ],

    /*
     * Reasons that end up in the "Drive" column of the results table and in the
     * run's warnings.
     */
    'drive' => [
        'not_connected' => 'No Google account is connected. Connect one in Settings.',
        'no_refresh_token' => 'Google did not return a refresh token. Revoke the app in your Google account and connect it again.',
        'request_failed' => 'Google Drive answered :status on :operation. :detail',

        'skipped' => [
            'no_date' => 'Not uploaded: the CFDI has no issue date, so there is no year to file it under.',
            'unknown_type' => 'Not uploaded: your RFC is not on the CFDI, so it is neither issued nor received.',
            'missing_file' => 'Not uploaded: the file is no longer where it was.',
        ],

        'warning' => [
            'some_failed' => ':count file(s) could not be uploaded to Google Drive. You can retry from the table.',
        ],
    ],

    'error' => [
        'invalid_file_name' => 'Invalid file name.',
        'run_produced_no_files' => 'The run produced no files.',
        'zip_failed' => 'The ZIP could not be created.',
        'unreadable_file' => 'Could not read :path.',
        'run_not_finished' => 'The run has not finished yet.',
        'run_files_cleaned' => 'The files for this run have already been cleaned up.',
        'file_moved' => 'The file is no longer at its location.',
    ],
];
