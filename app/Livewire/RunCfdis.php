<?php

namespace App\Livewire;

use App\Cfdi\CfdiProcessor;
use App\Cfdi\Data\CollectionError;
use App\Cfdi\Data\FileStatus;
use App\Cfdi\Data\ProcessOptions;
use App\Cfdi\Data\ProcessResult;
use App\Cfdi\Data\TypeLetter;
use App\Cfdi\FileCollector;
use App\Cfdi\Naming\NameSanitizer;
use App\Cfdi\Naming\Placeholder;
use App\Cfdi\Naming\SuggestedNamePrompt;
use App\Cfdi\Naming\TemplateExample;
use App\Cfdi\Support\Rfc;
use App\Models\Counterparty;
use App\Models\Document;
use App\Models\Run;
use App\Models\RunFile;
use App\Support\AppSettings;
use App\Support\Documents\DocumentArchiver;
use App\Support\Runs\RunAnalyzer;
use App\Support\Runs\RunStorage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL as UrlGenerator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Title('Procesar CFDIs')]
final class RunCfdis extends Component
{
    use WithFileUploads;

    public const string STEP_UPLOAD = Run::STEP_UPLOAD;

    public const string STEP_COUNTERPARTIES = Run::STEP_COUNTERPARTIES;

    public const string STEP_RESULTS = Run::STEP_RESULTS;

    /**
     * Tope duro de archivos.
     *
     * No es cosmetico: `max_file_uploads` de PHP vale 20 por default y Livewire
     * manda todo el `wire:model` en un solo request, asi que PHP truncaria el
     * excedente en silencio. Con la regla, el usuario ve el limite.
     */
    public const int MAX_FILES = 20;

    /** @var array<int, TemporaryUploadedFile> */
    public array $files = [];

    /**
     * Buzon de entrada de las subidas.
     *
     * Tanto el `<input type="file">` como el drag & drop escriben aqui, y
     * `updatedIncomingFiles()` acumula en `$files`. Sin este paso intermedio
     * cada seleccion reemplazaria la anterior, que es el comportamiento nativo
     * de un input de archivos.
     *
     * @var array<int, TemporaryUploadedFile>
     */
    public array $incomingFiles = [];

    #[Url(as: 'corrida', except: '')]
    public string $runId = '';

    public string $step = self::STEP_UPLOAD;

    /** @var array<int, array{rfc: string, legalName: string|null, name: string}> */
    public array $newCounterparties = [];

    /** @var array<int, string> */
    public array $warnings = [];

    public int $totalFiles = 0;

    public int $okFiles = 0;

    public int $ignoredFiles = 0;

    public function mount(): void
    {
        if ($this->runId === '') {
            return;
        }

        $run = Run::query()->find($this->runId);

        if ($run === null) {
            // Corrida desconocida: la URL trae un uuid que no existe.
            $this->runId = '';

            return;
        }

        $this->step = $run->step;
        $this->newCounterparties = $run->new_counterparties;
        $this->warnings = $run->warnings;
        $this->totalFiles = $run->total_files;
        $this->okFiles = $run->ok_files;
        $this->ignoredFiles = $run->ignored_files;
    }

    /**
     * Mueve lo recien subido a la seleccion acumulada.
     *
     * No revalida tamanio ni tipo: `livewire.temporary_file_upload.rules` ya
     * rechaza en la subida lo que pase de 20 MB, y `analyze()` vuelve a validar
     * `files.*` antes de procesar.
     */
    public function updatedIncomingFiles(): void
    {
        $this->resetErrorBag('files');

        $remaining = self::MAX_FILES - count($this->files);
        $ignored = 0;

        foreach ($this->incomingFiles as $file) {
            if ($this->isAlreadySelected($file)) {
                continue;
            }

            if ($remaining <= 0) {
                $ignored++;

                continue;
            }

            $this->files[] = $file;
            $remaining--;
        }

        // Deja el buzon libre para el siguiente drop.
        $this->incomingFiles = [];

        if ($ignored > 0) {
            $this->addError('files', 'Máximo '.self::MAX_FILES.' archivos por corrida; se ignoraron '.$ignored.'.');
        }
    }

    public function removeFile(int $index): void
    {
        unset($this->files[$index]);

        $this->files = array_values($this->files);
        $this->resetValidation();
    }

    /**
     * Fase A: sube, analiza y decide si hace falta preguntar. No escribe nada en
     * `salida/` ni toca la tabla `counterparties`.
     */
    public function analyze(
        AppSettings $settings,
        RunStorage $storage,
        FileCollector $collector,
        RunAnalyzer $analyzer,
        DocumentArchiver $archiver,
    ): void {
        $myRfc = Rfc::normalize($settings->rfc());

        if ($myRfc === '') {
            $this->addError('files', 'Configura tu RFC en Ajustes antes de procesar.');

            return;
        }

        $this->validate();

        $this->runId = $storage->create();
        $this->persist();

        $paths = [];

        foreach ($this->files as $file) {
            $paths[] = $storage->storeInput($this->runId, $file);
        }

        // Libera el payload y evita un reenvio accidental.
        $this->files = [];

        $collected = $collector->collect($paths);

        // Las filas de recoleccion no se guardan aqui: `process()` vuelve a
        // recolectar sobre `entrada/` y reproduce exactamente los mismos errores.
        if ($collected->paths !== [] && ($unknown = $analyzer->analyze($collected->paths, $myRfc)) !== []) {
            $this->newCounterparties = array_map(fn ($item): array => [
                'rfc' => $item->rfc,
                'legalName' => $item->legalName,
                'name' => $item->suggestion,
            ], $unknown);

            $this->step = self::STEP_COUNTERPARTIES;
            $this->persist();

            return;
        }

        // Nada que preguntar: la fase A encadena directo a la fase B.
        $this->process($settings, $storage, $collector, $archiver);
    }

    /**
     * Fase B: guarda las contrapartes nuevas y corre el orquestador.
     */
    public function confirmCounterparties(
        NameSanitizer $sanitizer,
        AppSettings $settings,
        RunStorage $storage,
        FileCollector $collector,
        DocumentArchiver $archiver,
    ): void {
        $this->validate([
            'newCounterparties.*.name' => ['required', 'string', 'max:255'],
        ], [
            'newCounterparties.*.name.required' => 'Escribe un nombre para cada contraparte.',
        ]);

        foreach ($this->newCounterparties as $row) {
            $rfc = Rfc::normalize($row['rfc']);

            Counterparty::query()->updateOrCreate(
                ['rfc' => $rfc],
                [
                    'name' => $sanitizer->sanitize($row['name']) ?: $rfc,
                    'legal_name' => $row['legalName'],
                ],
            );
        }

        $this->process($settings, $storage, $collector, $archiver);
    }

    public function startOver(): void
    {
        $this->reset(['runId', 'step', 'newCounterparties', 'warnings', 'totalFiles', 'okFiles', 'ignoredFiles', 'files', 'incomingFiles']);
        $this->resetValidation();

        unset($this->allRows);
    }

    #[Computed]
    public function configuredRfc(): ?string
    {
        $rfc = Rfc::normalize(app(AppSettings::class)->rfc());

        return $rfc === '' ? null : $rfc;
    }

    #[Computed]
    public function template(): string
    {
        return app(AppSettings::class)->template();
    }

    /**
     * La plantilla partida en piezas, para dibujarla con una pill por placeholder.
     *
     * @return list<array{placeholder: Placeholder|null, text: string}>
     */
    #[Computed]
    public function templateSegments(): array
    {
        return Placeholder::segments($this->template);
    }

    /** Nombre de ejemplo, siempre como gasto: el selector G/I vive en Ajustes. */
    #[Computed]
    public function templateExample(): string
    {
        return TemplateExample::name($this->template, TypeLetter::Expense);
    }

    #[Computed]
    public function downloadUrl(): ?string
    {
        if ($this->okFiles === 0 || $this->runId === '') {
            return null;
        }

        // `cfdi:clean` ya se llevo `salida/`: la corrida sigue en el historial,
        // pero su ZIP ya no se puede armar. Los documentos archivados si siguen.
        if (Run::query()->whereKey($this->runId)->whereNotNull('files_purged_at')->exists()) {
            return null;
        }

        return UrlGenerator::temporarySignedRoute('runs.download', now()->addHours(2), ['run' => $this->runId]);
    }

    /**
     * Las filas de la tabla de resultados, ya persistidas.
     *
     * Los descartes de la recoleccion van primero, igual que en el comando: ese
     * orden esta congelado en `position`.
     *
     * @return Collection<int, RunFile>
     */
    #[Computed]
    public function allRows(): Collection
    {
        if ($this->runId === '') {
            return collect();
        }

        return RunFile::query()
            ->with('document')
            ->where('run_id', $this->runId)
            ->orderBy('position')
            ->get();
    }

    /**
     * Enlace firmado para bajar un archivo suelto de la tabla de resultados.
     */
    public function documentUrl(?Document $document): ?string
    {
        if ($document === null || ! $document->fileExists()) {
            return null;
        }

        return UrlGenerator::temporarySignedRoute(
            'documents.download',
            now()->addHours(2),
            ['document' => $document->id],
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:'.self::MAX_FILES],
            'files.*' => ['required', 'file', 'max:20480'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'files.required' => 'Selecciona al menos un archivo.',
            'files.min' => 'Selecciona al menos un archivo.',
            'files.max' => 'Máximo '.self::MAX_FILES.' archivos por corrida.',
            'files.*.required' => 'Alguno de los archivos no se subió bien.',
            'files.*.max' => 'Cada archivo debe pesar máximo 20 MB.',
        ];
    }

    private function process(
        AppSettings $settings,
        RunStorage $storage,
        FileCollector $collector,
        DocumentArchiver $archiver,
    ): void {
        // Sin esto, un segundo envio generaria duplicados ` (2)` encima de los
        // archivos de la primera pasada.
        $storage->resetOutput($this->runId);

        $collected = $collector->collect($storage->inputFiles($this->runId));

        $result = app(CfdiProcessor::class)->process(
            $collected->paths,
            new ProcessOptions(
                myRfc: (string) $this->configuredRfc(),
                template: $settings->template(),
                outputDirectory: $storage->outputPath($this->runId),
                move: false, // En la web siempre se copia: los originales son subidas temporales.
            ),
            new SuggestedNamePrompt,
        );

        // En la web la copia del archivo es la unica que va a quedar: los
        // originales son subidas temporales que `cfdi:clean` borra.
        $documents = $archiver->archive($result, copyIntoArchive: true);

        $this->warnings = $result->warnings;
        $this->totalFiles = $result->totalFiles;
        $this->okFiles = $result->okFiles;
        $this->ignoredFiles = count($collected->errors);
        $this->step = self::STEP_RESULTS;

        $this->persist();
        $this->persistFiles($collected->errors, $result, $documents);

        unset($this->allRows);
    }

    private function persist(): void
    {
        Run::query()->updateOrCreate(['id' => $this->runId], [
            'step' => $this->step,
            'total_files' => $this->totalFiles,
            'ok_files' => $this->okFiles,
            'ignored_files' => $this->ignoredFiles,
            'warnings' => $this->warnings,
            'new_counterparties' => $this->newCounterparties,
        ]);
    }

    /**
     * Reescribe las filas de la corrida. Un segundo envio del formulario las
     * reemplaza en vez de acumularlas.
     *
     * @param  list<CollectionError>  $errors
     * @param  array<int, Document>  $documents
     */
    private function persistFiles(array $errors, ProcessResult $result, array $documents): void
    {
        RunFile::query()->where('run_id', $this->runId)->delete();

        $rows = [];
        $position = 0;

        foreach ($errors as $error) {
            $rows[] = [
                'run_id' => $this->runId,
                'document_id' => null,
                'position' => $position++,
                'status' => FileStatus::Rejected->value,
                'original_name' => $error->subject,
                'new_name' => null,
                'note' => $error->reason,
            ];
        }

        foreach ($result->rows as $index => $row) {
            $rows[] = [
                'run_id' => $this->runId,
                'document_id' => ($documents[$index] ?? null)?->id,
                'position' => $position++,
                'status' => $row->status->value,
                'original_name' => $row->original,
                'new_name' => $row->newName,
                'note' => $row->note,
            ];
        }

        if ($rows !== []) {
            RunFile::query()->insert($rows);
        }
    }

    /**
     * Mismo nombre y mismo tamanio: soltar dos veces la misma carpeta no debe
     * gastar dos lugares del tope.
     */
    private function isAlreadySelected(TemporaryUploadedFile $candidate): bool
    {
        foreach ($this->files as $file) {
            if ($file->getClientOriginalName() === $candidate->getClientOriginalName()
                && $file->getSize() === $candidate->getSize()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Color del badge por estado.
     */
    public function statusColor(string $status): string
    {
        return match ($status) {
            FileStatus::Ok->value => 'green',
            FileStatus::Review->value => 'amber',
            default => 'red',
        };
    }

    public function isRejected(string $status): bool
    {
        return $status === FileStatus::Rejected->value;
    }

    public function statusIcon(string $status): string
    {
        return match ($status) {
            FileStatus::Ok->value => 'check',
            FileStatus::Review->value => 'exclamation-triangle',
            default => 'x-mark',
        };
    }
}
