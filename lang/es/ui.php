<?php

return [
    'nav' => [
        'process' => 'Procesar',
        'documents' => 'Documentos',
        'counterparties' => 'Contrapartes',
        'settings' => 'Ajustes',
    ],

    'common' => [
        'actions' => 'Acciones',
        'amount' => 'Monto',
        'cancel' => 'Cancelar',
        'counterparty' => 'Contraparte',
        'date' => 'Fecha',
        'delete' => 'Borrar',
        'download' => 'Descargar',
        'legal_name' => 'Razón social',
        'name' => 'Nombre',
        'new_name' => 'Nuevo nombre',
        'original' => 'Original',
        'rfc' => 'RFC',
        'save' => 'Guardar',
        'status' => 'Estado',
        'type' => 'Tipo',
    ],

    'settings' => [
        'title' => 'Ajustes',
        'subheading' => 'Tu RFC y la plantilla con la que se renombran los CFDIs.',
        'rfc_label' => 'Tu RFC',
        'rfc_description' => 'Se usa para saber si eres el emisor (ingreso) o el receptor (gasto). Se guarda en mayúsculas.',
        'template_heading' => 'Plantilla del nombre',
        'template_subheading' => 'Cómo se renombra cada CFDI procesado.',
        'template_label' => 'Formato',
        'letter_income_label' => 'Etiqueta de ingreso',
        'letter_expense_label' => 'Etiqueta de gasto',
        'letters_description' => 'Lo que :token escribe en el nombre. Por defecto es I para ingresos y G para gastos; o lo que quieras hasta :max caracteres.',
        'add_piece' => 'Agregar una pieza donde tengas el cursor',
        'restore_default' => 'Restaurar la predeterminada',
        'unknown_placeholder' => 'Un :token que escribas a mano y no esté en la lista se deja tal cual en el nombre.',
        'example' => 'Ejemplo',
        'expense' => 'Gasto (:letter)',
        'income' => 'Ingreso (:letter)',
        'saved' => 'Ajustes guardados.',
    ],

    'counterparties' => [
        'title' => 'Contrapartes',
        'subheading' => 'Los nombres cortos que se usan al renombrar. Se van guardando conforme procesas CFDIs.',
        'search_placeholder' => 'Buscar por RFC, nombre o razón social',
        'no_matches' => 'Ninguna contraparte coincide con «:search».',
        'empty' => 'Aún no hay contrapartes. Se van guardando conforme procesas CFDIs.',
        'documents' => 'Documentos',
        'rename' => 'Renombrar',
        'rename_heading' => 'Renombrar contraparte',
        'rename_subheading' => 'Este nombre es el que aparece en el nombre del archivo.',
        'delete_heading' => '¿Borrar la contraparte?',
        'delete_subheading' => 'Se vuelve a sugerir un nombre la próxima vez que aparezca en un CFDI.',
        'updated' => 'Contraparte actualizada.',
        'deleted' => 'Contraparte eliminada.',
    ],

    'documents' => [
        'title' => 'Documentos',
        'subheading' => 'Los CFDIs que ya procesaste, guardados y asignados a su contraparte.',
        'search_placeholder' => 'Buscar por nombre o folio fiscal',
        'all_counterparties' => 'Todas las contrapartes',
        'all_types' => 'Ingresos y gastos',
        'no_matches' => 'Ningún documento coincide con el filtro.',
        'empty' => 'Aún no hay documentos. Se van archivando conforme procesas CFDIs.',
        'file_not_found' => 'Archivo no encontrado',
        'delete_heading' => '¿Sacarlo del archivo?',
        'delete_subheading' => 'Si la app guardó su propia copia, se borra. Los archivos que solo están indexados desde tu carpeta no se tocan.',
        'deleted' => 'Documento eliminado del archivo.',

        'type' => [
            'expense' => 'Gastos (:letter)',
            'income' => 'Ingresos (:letter)',
            'unknown' => 'Sin determinar (?)',
        ],
    ],

    'run' => [
        'title' => 'Procesar CFDIs',
        'subheading' => 'Sube los PDF y XML. El XML manda; el PDF es sólo respaldo.',
        'missing_rfc_heading' => 'Falta configurar tu RFC',
        'missing_rfc_text' => 'Sin tu RFC no se puede saber quién es la contraparte ni si cada CFDI es un ingreso o un gasto.',
        'go_to_settings' => 'Ir a Ajustes',
        'your_rfc' => 'Tu RFC',
        'change' => 'Cambiar',
        'naming_preview' => 'Así se van a llamar tus archivos',
        'example' => 'Ejemplo:',
        'drop_heading' => 'Arrastra tus PDF y XML aquí',
        'drop_text' => 'Suéltalos en cualquier parte de la pantalla; también sirve una carpeta completa.',
        'pick_files' => 'Selecciona archivos',
        'limits' => 'Hasta :max archivos, 20 MB cada uno.',
        'uploading' => 'Subiendo…',
        'process' => 'Procesar',
        'processing' => 'Procesando…',
        'overlay_heading' => 'Suelta tus PDF y XML aquí',
        'overlay_text' => 'Se agregan a los que ya tienes seleccionados.',
        'new_counterparties' => 'Contrapartes nuevas',
        'new_counterparties_text' => 'Estos RFC aparecen por primera vez. El nombre propuesto sale de la razón social; edítalo si quieres.',
        'name_to_use' => 'Nombre a usar',
        'save_and_process' => 'Guardar y procesar',
        'no_files' => 'No hay archivos PDF/XML que procesar.',
        'warnings' => 'Advertencias',
        'summary' => ':ok/:total procesados correctamente.',
        'ignored' => ':count archivo(s) ignorado(s) por extensión no soportada.',
        'download_zip' => 'Descargar ZIP',
        'start_over' => 'Procesar otros archivos',
    ],

    'cloud' => [
        'heading' => 'Almacenamiento en la nube',
        'subheading' => 'Sube a :provider los CFDIs que se procesaron bien, ordenados por año y por tipo.',
        'provider_label' => 'Proveedor',
        'enable' => 'Subir a :provider',
        'enable_description' => 'Apagado, nada sale de esta máquina y el resto de la app funciona igual.',
        'connected_as' => 'Conectado como :account',
        'not_connected' => 'Todavía no has conectado una cuenta de :provider.',
        'connect' => 'Conectar con :provider',
        'reconnect' => 'Reconectar',
        'disconnect' => 'Desconectar',
        'disconnected' => 'Se desconectó la cuenta de :provider.',
        'connected' => 'Cuenta conectada: :account',
        'connected_unknown' => 'Cuenta de :provider conectada.',
        'cancelled' => 'No se autorizó el acceso a :provider.',
        'invalid_state' => 'La respuesta del proveedor no corresponde a esta sesión.',
        'income_folder_label' => 'Carpeta de emitidas',
        'expense_folder_label' => 'Carpeta de recibidas',
        'folders_description' => 'Usa :token donde vaya el año. Las carpetas que falten se crean solas.',
        'preview' => 'Este año se usarían',
        'verify' => 'Verificar carpetas',
        'verified' => 'Listo: :income y :expense.',
        'column' => 'Nube',
        'open' => 'Ver en :provider',
        'retry' => 'Reintentar subida',
        'retried' => 'Se reintentó la subida.',

        // El nombre visible de cada proveedor vive aquí y en ningún otro lado:
        // es lo que lee `CloudProvider::label()`, que es lo que hace que todos
        // los `:provider` de arriba digan lo correcto de una sola vez.
        'provider' => [
            'google_drive' => 'Google Drive',
        ],

        // Texto que solo es CIERTO de un proveedor. Una frase cuya forma es la
        // misma para todos va arriba con un marcador :provider.
        'google_drive' => [
            'root_folder_label' => 'Carpeta raíz',
            'root_folder_description' => 'El ID de la carpeta que contiene tus carpetas de año. Se saca de la URL de la carpeta en Drive. Déjalo vacío para usar el tope de «Mi unidad».',
            'scope_notice' => 'Google pide el permiso completo de Drive porque el permiso reducido no puede ver carpetas que no creó esta app. Deja la pantalla de consentimiento en modo «Testing» con tu cuenta como usuario de prueba.',
            'missing_credentials' => 'Faltan GOOGLE_CLIENT_ID y GOOGLE_CLIENT_SECRET en el archivo .env.',
            'wrong_host' => 'Google no acepta dominios .test, así que el callback vuelve a :host. Abre :url para conectar la cuenta desde ahí (arranca el servidor con «php artisan serve»). Una vez conectada, la integración funciona igual desde este dominio.',
        ],

        'status' => [
            'uploaded' => 'Subido',
            'skipped' => 'No subido',
            'failed' => 'Falló',
        ],
    ],

    'validation' => [
        'rfc' => [
            'required' => 'Escribe tu RFC.',
            'size' => 'El RFC debe tener 12 o 13 caracteres.',
            'format' => 'El RFC no tiene un formato valido.',
            'not_configured' => 'Configura tu RFC en Ajustes antes de procesar.',
        ],

        'template' => [
            'required' => 'Escribe una plantilla.',
            'max' => 'La plantilla es demasiado larga.',
            'placeholder' => 'La plantilla debe incluir al menos un placeholder, por ejemplo :token.',
        ],

        'letter' => [
            'required' => 'Escribe una etiqueta para ingresos y otra para gastos.',
            'max' => 'La etiqueta debe tener máximo :max caracteres.',
            'format' => 'La etiqueta no puede llevar ninguno de estos caracteres: / \\ : * ? " < > |',
        ],

        'counterparty' => [
            'name_required' => 'Escribe un nombre.',
            'names_required' => 'Escribe un nombre para cada contraparte.',
        ],

        'files' => [
            'required' => 'Selecciona al menos un archivo.',
            'max' => 'Máximo :max archivos por corrida.',
            'ignored' => 'Máximo :max archivos por corrida; se ignoraron :count.',
            'item_required' => 'Alguno de los archivos no se subió bien.',
            'item_max' => 'Cada archivo debe pesar máximo 20 MB.',
        ],
        'cloud' => [
            'not_connected' => 'Conecta una cuenta de :provider antes de activar la integración.',
            'folder_required' => 'Escribe un nombre de carpeta.',
            'folder_token' => 'El nombre de la carpeta debe incluir :token, o todos los años irían a la misma carpeta.',
            'folder_max' => 'El nombre de la carpeta debe tener máximo :max caracteres.',
            'folder_format' => 'El nombre de la carpeta no puede llevar / ni \\.',
        ],

    ],
];
