<?php

return [
    'autofile' => [
        'description' => 'Renombra CFDIs (PDF + XML) segun una plantilla configurable.',

        'option' => [
            'files' => 'Archivos PDF/XML a procesar.',
            'dir' => 'Procesa una carpeta completa (no recursivo).',
            'output' => 'Carpeta de salida (default ./Renombradas).',
            'move' => 'Mueve en vez de copiar.',
            'no_interactive' => 'No pregunta nada; usa sugerencias automaticas.',
            'rfc' => 'RFC propio para esta corrida (no lo guarda).',
            'template' => 'Plantilla para esta corrida (no la guarda).',
            'set_rfc' => 'Guarda tu RFC y termina.',
            'set_template' => 'Guarda la plantilla default y termina.',
            'letter_income' => 'Etiqueta que {type} escribe para ingresos en esta corrida (no se guarda).',
            'letter_expense' => 'Etiqueta que {type} escribe para gastos en esta corrida (no se guarda).',
            'set_letter_income' => 'Guarda la etiqueta de ingresos y termina.',
            'set_letter_expense' => 'Guarda la etiqueta de gastos y termina.',
        ],

        'rfc_saved' => 'RFC guardado: :rfc',
        'template_saved' => 'Plantilla guardada: :template',
        'letters_saved' => 'Etiquetas guardadas: :income para ingresos, :expense para gastos.',
        'invalid_letter' => 'Error: la etiqueta debe tener de 1 a :max caracteres y no puede llevar / \\ : * ? " < > |',
        'nothing_to_process' => 'No hay archivos PDF/XML que procesar.',
        'output_directory' => 'Salida: :directory',
        'warnings' => 'Advertencias:',
        'summary' => ':ok/:total procesados correctamente.',
        'no_rfc_configured' => 'Error: no hay RFC configurado. Usa --set-rfc o --rfc.',
        'ask_rfc' => 'Tu RFC (se guarda para la proxima): ',
        'rfc_required' => 'Error: se necesita tu RFC para saber quien es la contraparte.',

        'drive' => [
            'heading' => 'Google Drive:',
            'uploaded' => '  :name -> :link',
            'not_uploaded' => '  :name: :reason',
            'summary' => ':count/:total subidos a Google Drive.',
        ],

        'table' => [
            'status' => 'Estado',
            'original' => 'Original',
            'new_name' => 'Nuevo nombre',
            'note' => 'Nota',
        ],
    ],

    'clean' => [
        'description' => 'Libera el espacio de las corridas de la web con mas de N horas.',

        'option' => [
            'hours' => 'Antiguedad minima, en horas, de las corridas a limpiar.',
        ],

        'run_cleaned' => 'Limpiada la corrida :id',
        'no_old_runs' => 'No hay corridas viejas que limpiar.',
        'runs_cleaned' => ':count corrida(s) limpiada(s).',
    ],

    'prompt' => [
        'new_counterparty' => "Contraparte nueva :rfc:legal\n  Nombre a usar [:suggestion]: ",
    ],
];
