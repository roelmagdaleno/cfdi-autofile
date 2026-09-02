<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Descarga un CFDI archivado.
 *
 * A diferencia de una corrida, el id de un documento es un entero secuencial y
 * si es enumerable, asi que el enlace va firmado y expira. La entrada es el id,
 * nunca una ruta: `path` sale de la fila, no del request.
 */
final class DownloadDocumentController extends Controller
{
    public function __invoke(Document $document): BinaryFileResponse
    {
        // Un documento indexado en el disco del usuario (el comando) puede haber
        // sido movido o borrado desde afuera sin que la app se entere.
        abort_unless($document->fileExists(), 404, 'El archivo ya no está en su ubicación.');

        return response()->download($document->absolutePath(), $document->name);
    }
}
