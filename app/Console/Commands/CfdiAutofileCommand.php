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
    protected $signature = 'cfdi:autofile
        {files?* : Archivos PDF/XML a procesar.}
        {--dir= : Procesa una carpeta completa (no recursivo).}
        {--o|output= : Carpeta de salida (default ./Renombradas).}
        {--move : Mueve en vez de copiar.}
        {--no-interactive : No pregunta nada; usa sugerencias automaticas.}
        {--rfc= : RFC propio para esta corrida (no lo guarda).}
        {--template= : Plantilla para esta corrida (no la guarda).}
        {--set-rfc= : Guarda tu RFC y termina.}
        {--set-template= : Guarda la plantilla default y termina.}';

    protected $description = 'Renombra CFDIs (PDF + XML) segun una plantilla configurable.';

    public function handle(
        AppSettings $settings,
        FileCollector $collector,
        CfdiProcessor $processor,
        DocumentArchiver $archiver,
    ): int {
        $setRfc = $this->stringOption('set-rfc');

        if ($setRfc !== null && $setRfc !== '') {
            $settings->setRfc($setRfc);
            $this->line("RFC guardado: {$settings->rfc()}");

            return self::SUCCESS;
        }

        $setTemplate = $this->stringOption('set-template');

        if ($setTemplate !== null && $setTemplate !== '') {
            $settings->setTemplate($setTemplate);
            $this->line("Plantilla guardada: {$settings->template()}");

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
        $output = $this->stringOption('output') ?: 'Renombradas';

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
            $this->line('No hay archivos PDF/XML que procesar.');

            return self::FAILURE;
        }

        $result = $processor->process(
            $collected->paths,
            new ProcessOptions($myRfc, $template, $output, (bool) $this->option('move')),
            $this->prompt($interactive),
        );

        // Solo se indexa: el archivo ya vive donde lo pidio `--output`, y copiarlo
        // otra vez al archivo interno duplicaria cada byte sin ganar nada.
        $archiver->archive($result, copyIntoArchive: false);

        $this->newLine();
        $this->line("Salida: {$result->outputDirectory}");
        $this->newLine();

        $this->renderTable([...$errorRows, ...$result->rows]);

        if ($result->warnings !== []) {
            $this->newLine();
            $this->line('Advertencias:');

            foreach ($result->warnings as $warning) {
                $this->output->writeln($warning, OutputInterface::OUTPUT_RAW);
            }
        }

        $this->newLine();
        $this->line("{$result->okFiles}/{$result->totalFiles} procesados correctamente.");

        return $result->allOk() ? self::SUCCESS : 2;
    }

    /**
     * `null` significa "ya se imprimio el error, sal con 1".
     */
    private function resolveMyRfc(AppSettings $settings, bool $interactive): ?string
    {
        $myRfc = Rfc::normalize($this->stringOption('rfc') ?: $settings->rfc());

        if ($myRfc !== '') {
            return $myRfc;
        }

        if (! $interactive) {
            $this->writeError('Error: no hay RFC configurado. Usa --set-rfc o --rfc.');

            return null;
        }

        $this->output->write('Tu RFC (se guarda para la proxima): ', false, OutputInterface::OUTPUT_RAW);
        $answer = (new ConsoleNamePrompt($this->input, $this->output->getOutput()))->readLine();
        $myRfc = Rfc::normalize($answer);

        if ($myRfc === '') {
            $this->writeError('Error: se necesita tu RFC para saber quien es la contraparte.');

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
            ['Estado', 'Original', 'Nuevo nombre', 'Nota'],
            array_map(fn (FileResult $row): array => [
                $row->status->value,
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
