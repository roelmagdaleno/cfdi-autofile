<?php

return [
    'autofile' => [
        'description' => 'Renames CFDIs (PDF + XML) using a configurable template.',

        'option' => [
            'files' => 'PDF/XML files to process.',
            'dir' => 'Process a whole folder (not recursive).',
            'output' => 'Output folder (default ./Renamed).',
            'move' => 'Move instead of copying.',
            'no_interactive' => 'Ask nothing; use the automatic suggestions.',
            'rfc' => 'Your own RFC for this run (not saved).',
            'template' => 'Template for this run (not saved).',
            'set_rfc' => 'Save your RFC and exit.',
            'set_template' => 'Save the default template and exit.',
        ],

        'rfc_saved' => 'Saved RFC: :rfc',
        'template_saved' => 'Saved template: :template',
        'nothing_to_process' => 'There are no PDF/XML files to process.',
        'output_directory' => 'Output: :directory',
        'warnings' => 'Warnings:',
        'summary' => ':ok/:total processed successfully.',
        'no_rfc_configured' => 'Error: no RFC is configured. Use --set-rfc or --rfc.',
        'ask_rfc' => 'Your RFC (saved for next time): ',
        'rfc_required' => 'Error: your RFC is needed to know who the counterparty is.',

        'table' => [
            'status' => 'Status',
            'original' => 'Original',
            'new_name' => 'New name',
            'note' => 'Note',
        ],
    ],

    'clean' => [
        'description' => 'Frees up the space used by web runs older than N hours.',

        'option' => [
            'hours' => 'Minimum age, in hours, of the runs to clean.',
        ],

        'run_cleaned' => 'Cleaned run :id',
        'no_old_runs' => 'There are no old runs to clean.',
        'runs_cleaned' => ':count run(s) cleaned.',
    ],

    'prompt' => [
        'new_counterparty' => "New counterparty :rfc:legal\n  Name to use [:suggestion]: ",
    ],
];
