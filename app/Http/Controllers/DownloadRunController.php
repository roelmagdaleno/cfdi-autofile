<?php

namespace App\Http\Controllers;

use App\Models\Run;
use App\Support\Runs\RunZipper;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Descarga el ZIP de una corrida.
 *
 * No hay usuario, asi que no hay a quien acotar la descarga. Las defensas son:
 * el id es un UUID v4 impredecible y la ruta solo hace match con forma de UUID
 * (nada de traversal ni enumeracion), el enlace va firmado y expira, y la app
 * corre en local. La unica entrada es el UUID: nunca una ruta ni un nombre de
 * archivo.
 */
final class DownloadRunController extends Controller
{
    public function __invoke(string $run, RunZipper $zipper): BinaryFileResponse
    {
        $model = Run::query()->find($run);

        abort_if($model === null, 404);
        abort_unless($model->isFinished(), 409, 'La corrida aún no ha terminado.');

        // La corrida sigue en el historial, pero `cfdi:clean` ya se llevo sus
        // archivos. Los CFDIs archivados siguen en /documentos.
        abort_unless($model->hasFiles(), 410, 'Los archivos de esta corrida ya se limpiaron.');

        return response()
            ->download($zipper->zip($run), RunZipper::FILENAME)
            ->deleteFileAfterSend();
    }
}
