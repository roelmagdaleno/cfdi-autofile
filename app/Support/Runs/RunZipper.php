<?php

namespace App\Support\Runs;

use RuntimeException;
use ZipArchive;

/**
 * Packs ONLY the output folder.
 *
 * Rejected files are never copied into `output/`, so excluding them from the ZIP
 * is structural: there is no filter that can be forgotten.
 */
final class RunZipper
{
    public function __construct(private readonly RunStorage $storage) {}

    /**
     * Name the ZIP is downloaded as. Translated, because the user sees it.
     */
    public static function filename(): string
    {
        return (string) __('cfdi.zip_filename');
    }

    public function zip(string $runId): string
    {
        $files = $this->storage->outputFiles($runId);

        if ($files === []) {
            throw new RuntimeException((string) __('cfdi.error.run_produced_no_files'));
        }

        $path = $this->storage->disk()->path("{$runId}/".self::filename());

        $archive = new ZipArchive;

        if ($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException((string) __('cfdi.error.zip_failed'));
        }

        foreach ($files as $file) {
            $archive->addFile($file, basename($file));
        }

        $archive->close();

        return $path;
    }
}
