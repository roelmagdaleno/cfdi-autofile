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
 * The core shared by the Artisan command and the web.
 *
 * It knows nothing about the terminal or HTTP: it takes absolute paths and
 * returns a result.
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

        // The mkdir lives here on purpose: that way the command's "nothing to
        // process" branch (which never calls the processor) does not create the
        // folder.
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
                // Rejected files are never copied or moved: they stay where they were.
                foreach ($group->files() as $file) {
                    $rows[] = FileResult::rejected(basename($file), $e->getMessage());
                }

                continue;
            }

            $reference = PartyResolver::counterpartyOf($data, $options->myRfc);
            $counterparty = $this->counterpartyResolver->resolve($reference->rfc, $reference->legalName, $prompt);
            $type = PartyResolver::typeLetter($data, $options->myRfc);

            if ($type === TypeLetter::Unknown) {
                // Before the loop, so both files of the group carry it.
                $data = $data->withWarning(
                    (string) __('cfdi.warning.rfc_not_a_party', ['rfc' => $options->myRfc]),
                );
            }

            foreach ($group->files() as $file) {
                $name = $this->nameBuilder->build(
                    $data,
                    $type,
                    $counterparty,
                    $options->template,
                    strtolower(PathExpander::suffix($file)),
                    $options->letters,
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
     * `name (2).ext`, `name (3).ext`, … The suffix goes before the extension.
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

            // `rename` fails across different filesystems.
            $this->copyPreservingMetadata($source, $destination);
            @unlink($source);

            return;
        }

        $this->copyPreservingMetadata($source, $destination);
    }

    /**
     * PHP's `copy()` preserves neither mtime nor permissions; Python's
     * `shutil.copy2` does.
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
