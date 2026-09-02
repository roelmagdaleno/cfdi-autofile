<?php

namespace App\Support\Runs;

use App\Cfdi\Support\PathExpander;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;

/**
 * Directorios de una corrida de la web: `{uuid}/entrada` y `{uuid}/salida`.
 *
 * TODO ninguno: todo pasa por `Storage::disk('runs')`, nunca por
 * `storage_path()`. Eso es lo que permite que `Storage::fake('runs')` le entregue
 * al orquestador un directorio real y aislado durante los tests. Una sola
 * llamada a `storage_path()` haria que los tests escribieran en el storage de
 * verdad.
 */
final class RunStorage
{
    public const string DISK = 'runs';

    public const string INPUT = 'entrada';

    public const string OUTPUT = 'salida';

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
     * Vacia la carpeta de salida.
     *
     * Es obligatorio llamarlo antes de cada proceso: sin esto, un segundo envio
     * del formulario generaria duplicados ` (2)` sobre los archivos del primero.
     */
    public function resetOutput(string $runId): void
    {
        $this->disk()->deleteDirectory("{$runId}/".self::OUTPUT);
        $this->disk()->makeDirectory("{$runId}/".self::OUTPUT);
    }

    /**
     * Mueve un archivo subido a `entrada/` conservando su nombre original.
     *
     * El nombre original importa: el emparejado PDF+XML es por nombre base, y la
     * columna "Original" lo muestra. Los nombres temporales de Livewire son
     * hashes, asi que hay que renombrarlo aqui.
     */
    public function storeInput(string $runId, TemporaryUploadedFile $file, ?string $originalName = null): string
    {
        $name = basename($originalName ?? $file->getClientOriginalName());

        if ($name === '' || $name === '.' || $name === '..' || str_contains($name, '/') || str_contains($name, '\\')) {
            throw new RuntimeException('Nombre de archivo invalido.');
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
     * Vacia los directorios de una corrida sin borrar su historial.
     *
     * La fila de `runs` y sus `run_files` se quedan: pesan bytes y son el unico
     * registro de que esa corrida existio. Lo que se va es lo pesado, que ya se
     * archivo en `documents` de todos modos.
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
     * Aplica el sufijo ` (2)` en vez de sobreescribir.
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
