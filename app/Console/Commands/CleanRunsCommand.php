<?php

namespace App\Console\Commands;

use App\Models\Run;
use App\Support\Runs\RunStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Empties the directories of old web runs.
 *
 * Deliberately not scheduled: there is no background work in this app. Run it by
 * hand whenever you want to free up space.
 *
 * It does NOT delete the history or the CFDI archive: the `runs` row stays with
 * `files_purged_at` set, and archived documents live on their own disk.
 */
final class CleanRunsCommand extends Command
{
    /**
     * Built in the constructor because `__()` is not a constant expression and
     * Symfony parses the signature inside `parent::__construct()`.
     */
    public function __construct()
    {
        $this->signature = 'cfdi:clean
            {--hours=24 : '.__('console.clean.option.hours').'}';

        $this->description = (string) __('console.clean.description');

        parent::__construct();
    }

    public function handle(RunStorage $storage): int
    {
        $hours = max(0, (int) $this->option('hours'));
        $cutoff = now()->subHours($hours);

        $removed = 0;

        // `<=` and not `<` so that `--hours=0` also cleans what was written in
        // this very second, i.e. "clean everything".
        $runs = Run::query()
            ->whereNull('files_purged_at')
            ->where('created_at', '<=', $cutoff)
            ->get();

        foreach ($runs as $run) {
            $storage->purge($run->id);
            $run->update(['files_purged_at' => now()]);

            $this->line((string) __('console.clean.run_cleaned', ['id' => $run->id]));
            $removed++;
        }

        $this->line($removed === 0
            ? (string) __('console.clean.no_old_runs')
            : (string) __('console.clean.runs_cleaned', ['count' => $removed]));

        $this->pruneLivewireTemp($cutoff->getTimestamp());

        return self::SUCCESS;
    }

    /**
     * Livewire temporaries left by a user who uploaded files and walked away.
     *
     * It only cleans those: phase A moves the files into `input/` and deletes the
     * temporary, so only the ones abandoned before submitting are left.
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
