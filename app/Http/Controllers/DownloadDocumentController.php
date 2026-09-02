<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Downloads an archived CFDI.
 *
 * Unlike a run, a document's id is a sequential integer and therefore
 * enumerable, so the link is signed and expires. The input is the id, never a
 * path: `path` comes from the row, not from the request.
 */
final class DownloadDocumentController extends Controller
{
    public function __invoke(Document $document): BinaryFileResponse
    {
        // A document indexed on the user's own disk (the command) may have been
        // moved or deleted from outside without the app noticing.
        abort_unless($document->fileExists(), 404, (string) __('cfdi.error.file_moved'));

        return response()->download($document->absolutePath(), $document->name);
    }
}
