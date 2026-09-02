<?php

namespace App\Support\Runs;

use App\Cfdi\Support\PathExpander;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;

/**
 * Directories of a web run: `{uuid}/input` and `{uuid}/output`.
 *
 * Everything goes through `Storage::disk('runs')`, never through
 * `storage_path()`. That is what lets `Storage::fake('runs')` hand the
 * orchestrator a real, isolated directory during tests. A single call to
 * `storage_path()` would make the tests write to the real storage.
 */
final class RunStorage
{
    public const string DISK = 'runs';

    public const string INPUT = 'input';

    public const string OUTPUT = 'output';

    public function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }

    public function create(): string
    {
        $runId = (string) Str::uuid();

        $this->disk()->makeDirectory("{$runId}/".self::INPUT);
        $this->disk()->makeDirectory("{$runId}/".self::OUTPUT);

        return $runId;
    }

    public function exists(string $runId): bool
    {
        return $this->disk()->directoryExists($runId);
    }

    public function inputPath(string $runId): string
    {
        return $this->disk()->path("{$runId}/".self::INPUT);
    }

    public function outputPath(string $runId): string
    {
        return $this->disk()->path("{$runId}/".self::OUTPUT);
    }

    /**
     * Empties the output folder.
     *
     * Calling it before each process is mandatory: without this, a second
     * submission of the form would produce ` (2)` duplicates on top of the files
     * from the first one.
     */
    public function resetOutput(string $runId): void
    {
        $this->disk()->deleteDirectory("{$runId}/".self::OUTPUT);
        $this->disk()->makeDirectory("{$runId}/".self::OUTPUT);
    }

    /**
     * Moves an uploaded file into `input/`, keeping its original name.
     *
     * The original name matters: PDF+XML pairing is done by base name, and the
     * "Original" column displays it. Livewire's temporary names are hashes, so
     * the file has to be renamed here.
     */
    public function storeInput(string $runId, TemporaryUploadedFile $file, ?string $originalName = null): string
    {
        $name = basename($originalName ?? $file->getClientOriginalName());

        if ($name === '' || $name === '.' || $name === '..' || str_contains($name, '/') || str_contains($name, '\\')) {
            throw new RuntimeException((string) __('cfdi.error.invalid_file_name'));
        }

        $directory = "{$runId}/".self::INPUT;
        $target = $this->freeName($directory, $name);

        $file->storeAs($directory, $target, ['disk' => self::DISK]);
        $file->delete();

        return $this->disk()->path("{$directory}/{$target}");
    }

    /**
     * @return list<string>
     */
    public function inputFiles(string $runId): array
    {
        return $this->filesIn("{$runId}/".self::INPUT);
    }

    /**
     * @return list<string>
     */
    public function outputFiles(string $runId): array
    {
        return $this->filesIn("{$runId}/".self::OUTPUT);
    }

    /**
     * Empties a run's directories without deleting its history.
     *
     * The `runs` row and its `run_files` stay: they weigh bytes and are the only
     * record that the run ever happened. What goes away is the heavy part, which
     * has been archived into `documents` anyway.
     */
    public function purge(string $runId): void
    {
        $this->disk()->deleteDirectory($runId);
    }

    /**
     * @return list<string>
     */
    private function filesIn(string $directory): array
    {
        if (! $this->disk()->directoryExists($directory)) {
            return [];
        }

        $files = array_map(
            fn (string $path): string => $this->disk()->path($path),
            $this->disk()->files($directory),
        );

        sort($files, SORT_STRING);

        return $files;
    }

    /**
     * Applies the ` (2)` suffix instead of overwriting.
     */
    private function freeName(string $directory, string $name): string
    {
        if (! $this->disk()->fileExists("{$directory}/{$name}")) {
            return $name;
        }

        $stem = PathExpander::stem($name);
        $suffix = PathExpander::suffix($name);

        for ($counter = 2; ; $counter++) {
            $candidate = "{$stem} ({$counter}){$suffix}";

            if (! $this->disk()->fileExists("{$directory}/{$candidate}")) {
                return $candidate;
            }
        }
    }
}
