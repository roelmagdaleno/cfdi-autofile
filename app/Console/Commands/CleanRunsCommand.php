<?php

namespace App\Console\Commands;

use App\Models\Run;
use App\Support\Runs\RunStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Vacia los directorios de las corridas viejas de la web.
 *
 * Sin scheduler a proposito: no hay trabajo en segundo plano en esta app.
 * Correlo a mano cuando quieras liberar espacio.
 *
 * NO borra el historial ni el archivo de CFDIs: la fila de `runs` se queda con
 * `files_purged_at`, y los documentos archivados viven en su propio disco.
 */
final class CleanRunsCommand extends Command
{
    protected $signature = 'cfdi:clean
        {--hours=24 : Antiguedad minima, en horas, de las corridas a limpiar.}';

    protected $description = 'Libera el espacio de las corridas de la web con mas de N horas.';

    public function handle(RunStorage $storage): int
    {
        $hours = max(0, (int) $this->option('hours'));
        $cutoff = now()->subHours($hours);

        $removed = 0;

        // `<=` y no `<` para que `--hours=0` limpie tambien lo escrito en este
        // mismo segundo, es decir "limpia todo".
        $runs = Run::query()
            ->whereNull('files_purged_at')
            ->where('created_at', '<=', $cutoff)
            ->get();

        foreach ($runs as $run) {
            $storage->purge($run->id);
            $run->update(['files_purged_at' => now()]);

            $this->line("Limpiada la corrida {$run->id}");
            $removed++;
        }

        $this->line($removed === 0
            ? 'No hay corridas viejas que limpiar.'
            : "{$removed} corrida(s) limpiada(s).");

        $this->pruneLivewireTemp($cutoff->getTimestamp());

        return self::SUCCESS;
    }

    /**
     * Los temporales de Livewire de un usuario que subio archivos y se fue.
     *
     * Nada mas los limpia: la fase A mueve los archivos a `entrada/` y borra el
     * temporal, asi que solo quedan los abandonados antes de enviar.
     */
    private function pruneLivewireTemp(int $cutoff): void
    {
        $disk = Storage::disk(config('livewire.temporary_file_upload.disk') ?: 'local');

        if (! $disk->directoryExists('livewire-tmp')) {
            return;
        }

        foreach ($disk->files('livewire-tmp') as $file) {
            if ($disk->lastModified($file) <= $cutoff) {
                $disk->delete($file);
            }
        }
    }
}
