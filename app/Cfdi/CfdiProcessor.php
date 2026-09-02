<?php

namespace App\Cfdi;

use App\Cfdi\Data\FileResult;
use App\Cfdi\Data\PlacedFile;
use App\Cfdi\Data\ProcessOptions;
use App\Cfdi\Data\ProcessResult;
use App\Cfdi\Data\TypeLetter;
use App\Cfdi\Exceptions\CfdiRejectedException;
use App\Cfdi\Naming\CounterpartyNamePrompt;
use App\Cfdi\Naming\CounterpartyResolver;
use App\Cfdi\Naming\FileNameBuilder;
use App\Cfdi\Naming\PartyResolver;
use App\Cfdi\Naming\SuggestedNamePrompt;
use App\Cfdi\Parsing\CfdiParserPipeline;
use App\Cfdi\Support\PathExpander;
use App\Cfdi\Support\Rfc;
use Illuminate\Support\Facades\File;

/**
 * El corazon compartido por el comando Artisan y la web.
 *
 * No conoce la terminal ni HTTP: recibe rutas absolutas y devuelve un resultado.
 */
final class CfdiProcessor
{
    public function __construct(
        private readonly CfdiParserPipeline $parser,
        private readonly FileGrouper $grouper,
        private readonly CounterpartyResolver $counterpartyResolver,
        private readonly FileNameBuilder $nameBuilder,
    ) {}

    /**
     * @param  list<string>  $paths
     */
    public function process(array $paths, ProcessOptions $options, ?CounterpartyNamePrompt $prompt = null): ProcessResult
    {
        $prompt ??= new SuggestedNamePrompt;

        // El mkdir vive aqui a proposito: asi la rama "no hay nada que procesar"
        // del comando (que nunca llama al procesador) no crea la carpeta.
        $directory = PathExpander::absolute($options->outputDirectory);
        File::ensureDirectoryExists($directory);
        $resolved = realpath($directory) ?: $directory;

        /** @var list<FileResult> $rows */
        $rows = [];
        /** @var list<string> $warnings */
        $warnings = [];
        $okFiles = 0;

        foreach ($this->grouper->group($paths) as $group) {
            try {
                $data = $this->parser->parse($group);
            } catch (CfdiRejectedException $e) {
                // Los rechazados nunca se copian ni se mueven: se quedan donde estaban.
                foreach ($group->files() as $file) {
                    $rows[] = FileResult::rejected(basename($file), $e->getMessage());
                }

                continue;
            }

            $reference = PartyResolver::counterpartyOf($data, $options->myRfc);
            $counterparty = $this->counterpartyResolver->resolve($reference->rfc, $reference->legalName, $prompt);
            $type = PartyResolver::typeLetter($data, $options->myRfc);

            if ($type === TypeLetter::Unknown) {
                // Antes del ciclo, para que los dos archivos del grupo la lleven.
                $data = $data->withWarning("tu RFC {$options->myRfc} no aparece como emisor ni receptor");
            }

            foreach ($group->files() as $file) {
                $name = $this->nameBuilder->build(
                    $data,
                    $type,
                    $counterparty,
                    $options->template,
                    strtolower(PathExpander::suffix($file)),
                );

                $destination = $this->freeDestination($resolved, $name);
                $this->place($file, $destination, $options->move);
                $okFiles++;

                $extension = ltrim(strtolower(PathExpander::suffix($file)), '.');

                $rows[] = FileResult::placed(
                    $data->confidence,
                    basename($file),
                    basename($destination),
                    $options->action(),
                    new PlacedFile(
                        path: $destination,
                        extension: $extension,
                        folio: $data->uuid,
                        issuedOn: FileNameBuilder::formatDate($data->date) ?: null,
                        total: FileNameBuilder::numericAmount($data->total),
                        type: $type,
                        counterpartyRfc: Rfc::normalize($reference->rfc) ?: null,
                        counterpartyName: $counterparty,
                        confidence: $data->confidence,
                        source: $data->source,
                    ),
                );

                foreach ($data->warnings as $warning) {
                    $warnings[] = '  ⚠ '.basename($file).": {$warning}";
                }
            }
        }

        return new ProcessResult($rows, $warnings, count($paths), $okFiles, $resolved);
    }

    /**
     * `nombre (2).ext`, `nombre (3).ext`, … El sufijo va antes de la extension.
     */
    private function freeDestination(string $directory, string $name): string
    {
        $candidate = $directory.'/'.$name;

        if (! file_exists($candidate)) {
            return $candidate;
        }

        $suffix = PathExpander::suffix($name);
        $stem = PathExpander::stem($name);

        for ($counter = 2; ; $counter++) {
            $candidate = $directory."/{$stem} ({$counter}){$suffix}";

            if (! file_exists($candidate)) {
                return $candidate;
            }
        }
    }

    private function place(string $source, string $destination, bool $move): void
    {
        if ($move) {
            if (@rename($source, $destination)) {
                return;
            }

            // `rename` falla entre sistemas de archivos distintos.
            $this->copyPreservingMetadata($source, $destination);
            @unlink($source);

            return;
        }

        $this->copyPreservingMetadata($source, $destination);
    }

    /**
     * `copy()` de PHP no preserva mtime ni permisos; `shutil.copy2` de Python si.
     */
    private function copyPreservingMetadata(string $source, string $destination): void
    {
        copy($source, $destination);

        $mtime = @filemtime($source);

        if ($mtime !== false) {
            @touch($destination, $mtime);
        }

        $mode = @fileperms($source);

        if ($mode !== false) {
            @chmod($destination, $mode & 0o7777);
        }
    }
}
