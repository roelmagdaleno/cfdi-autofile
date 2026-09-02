<?php

namespace App\Console\Commands;

use App\Cfdi\CfdiProcessor;
use App\Cfdi\Data\FileResult;
use App\Cfdi\Data\ProcessOptions;
use App\Cfdi\Data\ProcessResult;
use App\Cfdi\FileCollector;
use App\Cfdi\Naming\CounterpartyNamePrompt;
use App\Cfdi\Naming\SuggestedNamePrompt;
use App\Cfdi\Naming\TypeLetters;
use App\Cfdi\Support\Rfc;
use App\Console\Prompts\ConsoleNamePrompt;
use App\Cloud\Data\CloudStatus;
use App\Cloud\Data\CloudSyncResult;
use App\Cloud\CloudSync;
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
            {--letter-income= : '.__('console.autofile.option.letter_income').'}
            {--letter-expense= : '.__('console.autofile.option.letter_expense').'}
            {--set-rfc= : '.__('console.autofile.option.set_rfc').'}
            {--set-template= : '.__('console.autofile.option.set_template').'}
            {--set-letter-income= : '.__('console.autofile.option.set_letter_income').'}
            {--set-letter-expense= : '.__('console.autofile.option.set_letter_expense').'}';

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

        $setIncome = $this->stringOption('set-letter-income');
        $setExpense = $this->stringOption('set-letter-expense');

        if (($setIncome ?? '') !== '' || ($setExpense ?? '') !== '') {
            return $this->saveLetters($settings, $setIncome, $setExpense);
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

        $letters = $this->overlayLetters(
            $settings->typeLetters(),
            $this->stringOption('letter-income'),
            $this->stringOption('letter-expense'),
        );

        if (! $letters instanceof TypeLetters) {
            return self::FAILURE;
        }

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
            new ProcessOptions($myRfc, $template, $output, (bool) $this->option('move'), $letters),
            $this->prompt($interactive),
        );

        // Indexing only: the file already lives where `--output` asked, and
        // copying it into the internal archive again would duplicate every byte
        // for nothing.
        $documents = $archiver->archive($result, copyIntoArchive: false);

        // Uploading comes last and cannot fail the command: the files are
        // already renamed and in place.
        $drive = app(CloudSync::class)->sync($documents);

        $this->newLine();
        $this->line((string) __('console.autofile.output_directory', ['directory' => $result->outputDirectory]));
        $this->newLine();

        $this->renderTable([...$errorRows, ...$result->rows]);

        // The processor's warnings already carry the `  ⚠ {file}: ` prefix;
        // a Drive warning is about the run as a whole, so it gets the marker
        // here rather than a file name it does not have.
        $warnings = [
            ...$result->warnings,
            ...array_map(static fn (string $warning): string => '  ⚠ '.$warning, $drive->warnings),
        ];

        if ($warnings !== []) {
            $this->newLine();
            $this->line((string) __('console.autofile.warnings'));

            foreach ($warnings as $warning) {
                $this->output->writeln($warning, OutputInterface::OUTPUT_RAW);
            }
        }

        $this->renderDrive($result, $drive);

        $this->newLine();
        $this->line((string) __('console.autofile.summary', [
            'ok' => $result->okFiles,
            'total' => $result->totalFiles,
        ]));

        return $result->allOk() ? self::SUCCESS : 2;
    }

    /**
     * Where each file landed in Drive, or why it did not.
     *
     * Prints nothing at all when the integration is off, so the output of an
     * install that never touched it is byte for byte what it was before.
     */
    private function renderDrive(ProcessResult $result, CloudSyncResult $drive): void
    {
        if ($drive->outcomes === []) {
            return;
        }

        $this->newLine();
        $this->line((string) __('console.autofile.drive.heading'));

        $uploaded = 0;

        foreach ($drive->outcomes as $index => $outcome) {
            $name = $result->rows[$index]->newName ?? $result->rows[$index]->original;

            if ($outcome->status === CloudStatus::Uploaded) {
                $uploaded++;

                $this->output->writeln(
                    (string) __('console.autofile.drive.uploaded', ['name' => $name, 'link' => (string) $outcome->link]),
                    OutputInterface::OUTPUT_RAW,
                );

                continue;
            }

            $this->output->writeln(
                (string) __('console.autofile.drive.not_uploaded', ['name' => $name, 'reason' => (string) $outcome->reason]),
                OutputInterface::OUTPUT_RAW,
            );
        }

        $this->newLine();
        $this->line((string) __('console.autofile.drive.summary', [
            'count' => $uploaded,
            'total' => count($drive->outcomes),
        ]));
    }

    private function saveLetters(AppSettings $settings, ?string $income, ?string $expense): int
    {
        $letters = $this->overlayLetters($settings->typeLetters(), $income, $expense);

        if (! $letters instanceof TypeLetters) {
            return self::FAILURE;
        }

        $settings->setTypeLetters($letters);

        $saved = $settings->typeLetters();

        $this->line((string) __('console.autofile.letters_saved', [
            'income' => $saved->income,
            'expense' => $saved->expense,
        ]));

        return self::SUCCESS;
    }

    /**
     * The saved pair with the flags of this invocation applied on top.
     *
     * A flag that was not passed leaves the saved label alone, so overriding one
     * side does not silently reset the other. `null` means "the error has
     * already been printed, exit with 1".
     */
    private function overlayLetters(TypeLetters $saved, ?string $income, ?string $expense): ?TypeLetters
    {
        foreach ([$income, $expense] as $letter) {
            if (($letter ?? '') === '') {
                continue;
            }

            if (! TypeLetters::isValid((string) $letter)) {
                $this->writeError((string) __('console.autofile.invalid_letter', ['max' => TypeLetters::MAX_LENGTH]));

                return null;
            }
        }

        return new TypeLetters(
            income: ($income ?? '') === '' ? $saved->income : trim((string) $income),
            expense: ($expense ?? '') === '' ? $saved->expense : trim((string) $expense),
        );
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
