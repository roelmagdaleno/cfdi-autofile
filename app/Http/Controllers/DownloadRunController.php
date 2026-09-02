<?php

namespace App\Http\Controllers;

use App\Models\Run;
use App\Support\Runs\RunZipper;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Downloads a run's ZIP.
 *
 * There is no user, so there is nobody to scope the download to. The defenses
 * are: the id is an unpredictable UUID v4 and the route only matches a UUID
 * shape (no traversal, no enumeration), the link is signed and expires, and the
 * app runs locally. The only input is the UUID: never a path or a file name.
 */
final class DownloadRunController extends Controller
{
    public function __invoke(string $run, RunZipper $zipper): BinaryFileResponse
    {
        $model = Run::query()->find($run);

        abort_if($model === null, 404);
        abort_unless($model->isFinished(), 409, (string) __('cfdi.error.run_not_finished'));

        // The run is still in the history, but `cfdi:clean` has already taken its
        // files. The archived CFDIs are still under /documents.
        abort_unless($model->hasFiles(), 410, (string) __('cfdi.error.run_files_cleaned'));

        return response()
            ->download($zipper->zip($run), RunZipper::filename())
            ->deleteFileAfterSend();
    }
}
