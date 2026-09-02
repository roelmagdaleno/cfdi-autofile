<?php

namespace App\Cfdi\Data;

final class ProcessResult
{
    /**
     * @param  list<FileResult>  $rows
     * @param  list<string>  $warnings
     */
    public function __construct(
        public readonly array $rows,
        public readonly array $warnings,
        public readonly int $totalFiles,
        public readonly int $okFiles,
        public readonly string $outputDirectory,
    ) {}

    public function allOk(): bool
    {
        return $this->okFiles === $this->totalFiles;
    }
}
