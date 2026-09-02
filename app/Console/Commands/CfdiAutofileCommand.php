<?php

namespace App\Console\Commands;

use App\Cfdi\CfdiProcessor;
use App\Cfdi\Data\FileResult;
use App\Cfdi\Data\ProcessOptions;
use App\Cfdi\FileCollector;
use App\Cfdi\Naming\CounterpartyNamePrompt;
use App\Cfdi\Naming\SuggestedNamePrompt;
use App\Cfdi\Support\Rfc;
use App\Console\Prompts\ConsoleNamePrompt;
use App\Support\AppSettings;
use App\Support\Documents\DocumentArchiver;
use Illuminate\Console\Command;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class CfdiAutofileCommand extends Command
{
    /**
     * The signature and the description are built here, not declared as property
     * defaults: `__()` is not a constant expression, and Symfony parses the
     * signature inside `parent::__construct()`.
     *
     * The flag names themselves stay literal — they are the CLI contract. Only
     * the help text after each ` : ` is translated.
     */
    public function __construct()
    {
        $this->signature = 'cfdi:autofile
            {files?* : '.__('console.autofile.option.files').'}
            {--dir= : '.__('console.autofile.option.dir').'}
            {--o|output= : '.__('console.autofile.option.output').'}
            {--move : '.__('console.autofile.option.move').'}
            {--no-interactive : '.__('console.autofile.option.no_interactive').'}
            {--rfc= : '.__('console.autofile.option.rfc').'}
            {--template= : '.__('console.autofile.option.template').'}
            {--set-rfc= : '.__('console.autofile.option.set_rfc').'}
            {--set-template= : '.__('console.autofile.option.set_template').'}';

        $this->description = (string) __('console.autofile.description');

        parent::__construct();
    }

    public function handle(
        AppSettings $settings,
        FileCollector $collector,
        CfdiProcessor $processor,
        DocumentArchiver $archiver,
    ): int {
        $setRfc = $this->stringOption('set-rfc');

        if ($setRfc !== null && $setRfc !== '') {
            $settings->setRfc($setRfc);
            $this->line((string) __('console.autofile.rfc_saved', ['rfc' => (string) $settings->rfc()]));

            return self::SUCCESS;
        }

        $setTemplate = $this->stringOption('set-template');

        if ($setTemplate !== null && $setTemplate !== '') {
            $settings->setTemplate($setTemplate);
            $this->line((string) __('console.autofile.template_saved', ['template' => $settings->template()]));

            return self::SUCCESS;
        }

        /** @var list<string> $files */
        $files = $this->argument('files');
        $directory = $this->stringOption('dir');

        if ($files === [] && ($directory === null || $directory === '')) {
            $this->call('help', ['command_name' => 'cfdi:autofile']);

            return self::FAILURE;
        }

        $interactive = ! $this->option('no-interactive') && $this->input->isInteractive();

        $myRfc = $this->resolveMyRfc($settings, $interactive);

        if ($myRfc === null) {
            return self::FAILURE;
        }

        $template = $this->stringOption('template') ?: $settings->template();
        $output = $this->stringOption('output') ?: (string) __('cfdi.default_output_directory');

        $collected = $collector->collect($files, $directory);

        $errorRows = array_map(
            fn ($error): FileResult => FileResult::rejected($error->subject, $error->reason),
            $collected->errors,
        );

        if ($collected->paths === []) {
            if ($errorRows !== []) {
                $this->newLine();
                $this->renderTable($errorRows);
            }

            $this->newLine();
            $this->line((string) __('console.autofile.nothing_to_process'));

            return self::FAILURE;
        }

        $result = $processor->process(
            $collected->paths,
            new ProcessOptions($myRfc, $template, $output, (bool) $this->option('move')),
            $this->prompt($interactive),
        );

        // Indexing only: the file already lives where `--output` asked, and
        // copying it into the internal archive again would duplicate every byte
        // for nothing.
        $archiver->archive($result, copyIntoArchive: false);

        $this->newLine();
        $this->line((string) __('console.autofile.output_directory', ['directory' => $result->outputDirectory]));
        $this->newLine();

        $this->renderTable([...$errorRows, ...$result->rows]);

        if ($result->warnings !== []) {
            $this->newLine();
            $this->line((string) __('console.autofile.warnings'));

            foreach ($result->warnings as $warning) {
                $this->output->writeln($warning, OutputInterface::OUTPUT_RAW);
            }
        }

        $this->newLine();
        $this->line((string) __('console.autofile.summary', [
            'ok' => $result->okFiles,
            'total' => $result->totalFiles,
        ]));

        return $result->allOk() ? self::SUCCESS : 2;
    }

    /**
     * `null` means "the error has already been printed, exit with 1".
     */
    private function resolveMyRfc(AppSettings $settings, bool $interactive): ?string
    {
        $myRfc = Rfc::normalize($this->stringOption('rfc') ?: $settings->rfc());

        if ($myRfc !== '') {
            return $myRfc;
        }

        if (! $interactive) {
            $this->writeError((string) __('console.autofile.no_rfc_configured'));

            return null;
        }

        $this->output->write((string) __('console.autofile.ask_rfc'), false, OutputInterface::OUTPUT_RAW);
        $answer = (new ConsoleNamePrompt($this->input, $this->output->getOutput()))->readLine();
        $myRfc = Rfc::normalize($answer);

        if ($myRfc === '') {
            $this->writeError((string) __('console.autofile.rfc_required'));

            return null;
        }

        $settings->setRfc($myRfc);

        return $myRfc;
    }

    private function prompt(bool $interactive): CounterpartyNamePrompt
    {
        return $interactive
            ? new ConsoleNamePrompt($this->input, $this->output->getOutput())
            : new SuggestedNamePrompt;
    }

    /**
     * @param  list<FileResult>  $rows
     */
    private function renderTable(array $rows): void
    {
        $this->table(
            [
                __('console.autofile.table.status'),
                __('console.autofile.table.original'),
                __('console.autofile.table.new_name'),
                __('console.autofile.table.note'),
            ],
            array_map(fn (FileResult $row): array => [
                $row->status->label(),
                $row->original,
                $row->newName ?? '—',
                $row->note,
            ], $rows),
        );
    }

    private function writeError(string $message): void
    {
        $output = $this->output->getOutput();

        ($output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output)->writeln($message);
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) ? $value : null;
    }
}
