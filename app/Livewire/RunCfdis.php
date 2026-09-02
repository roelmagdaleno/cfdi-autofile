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
use App\Cfdi\Naming\TypeLetters;
use App\Cfdi\Support\Rfc;
use App\Drive\Data\DriveOutcome;
use App\Drive\Data\DriveStatus;
use App\Drive\DriveSync;
use App\Models\Counterparty;
use App\Models\Document;
use App\Models\Run;
use App\Models\RunFile;
use App\Support\AppSettings;
use App\Support\Documents\DocumentArchiver;
use App\Support\Runs\RunAnalyzer;
use App\Support\Runs\RunStorage;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL as UrlGenerator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * `#[Computed]` methods are reached as properties through Livewire's `__get`,
 * which is what memoizes them for the request; calling them as methods would
 * re-run the query every time. They are declared here so static analysis knows
 * the magic properties exist.
 *
 * @property-read string|null $configuredRfc
 * @property-read string $template
 * @property-read list<array{placeholder: Placeholder|null, text: string}> $templateSegments
 * @property-read string $templateExample
 * @property-read TypeLetters $typeLetters
 * @property-read string|null $downloadUrl
 * @property-read Collection<int, RunFile> $allRows
 * @property-read bool $hasFailedDriveUploads
 * @property-read bool $showsDriveColumn
 */
final class RunCfdis extends Component
{
    use WithFileUploads;

    public const string STEP_UPLOAD = Run::STEP_UPLOAD;

    public const string STEP_COUNTERPARTIES = Run::STEP_COUNTERPARTIES;

    public const string STEP_RESULTS = Run::STEP_RESULTS;

    /**
     * Hard cap on the number of files.
     *
     * It is not cosmetic: PHP's `max_file_uploads` defaults to 20 and Livewire
     * sends the whole `wire:model` in a single request, so PHP would silently
     * truncate the excess. With the rule, the user sees the limit.
     */
    public const int MAX_FILES = 20;

    /** @var array<int, TemporaryUploadedFile> */
    public array $files = [];

    /**
     * Inbox for the uploads.
     *
     * Both the `<input type="file">` and drag & drop write here, and
     * `updatedIncomingFiles()` accumulates into `$files`. Without this
     * intermediate step every selection would replace the previous one, which is
     * the native behavior of a file input.
     *
     * @var array<int, TemporaryUploadedFile>
     */
    public array $incomingFiles = [];

    #[Url(as: 'run', except: '')]
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
            // Unknown run: the URL carries a uuid that does not exist.
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
     * The page title is set here rather than with `#[Title]`: attribute
     * arguments must be constant, and this one is translated.
     */
    public function render(): View
    {
        return view('livewire.run-cfdis')->title(__('ui.run.title'));
    }

    /**
     * Moves what was just uploaded into the accumulated selection.
     *
     * It does not revalidate size or type: `livewire.temporary_file_upload.rules`
     * already rejects anything over 20 MB at upload time, and `analyze()`
     * validates `files.*` again before processing.
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

        // Leaves the inbox free for the next drop.
        $this->incomingFiles = [];

        if ($ignored > 0) {
            $this->addError('files', __('ui.validation.files.ignored', [
                'max' => self::MAX_FILES,
                'count' => $ignored,
            ]));
        }
    }

    public function removeFile(int $index): void
    {
        unset($this->files[$index]);

        $this->files = array_values($this->files);
        $this->resetValidation();
    }

    /**
     * Phase A: uploads, analyzes and decides whether anything has to be asked. It
     * writes nothing into `output/` and does not touch the `counterparties` table.
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
            $this->addError('files', __('ui.validation.rfc.not_configured'));

            return;
        }

        $this->validate();

        $this->runId = $storage->create();
        $this->persist();

        $paths = [];

        foreach ($this->files as $file) {
            $paths[] = $storage->storeInput($this->runId, $file);
        }

        // Frees the payload and avoids an accidental resubmission.
        $this->files = [];

        $collected = $collector->collect($paths);

        // The collection rows are not saved here: `process()` collects over
        // `input/` again and reproduces exactly the same errors.
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

        // Nothing to ask: phase A chains straight into phase B.
        $this->process($settings, $storage, $collector, $archiver);
    }

    /**
     * Phase B: saves the new counterparties and runs the orchestrator.
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
            'newCounterparties.*.name.required' => (string) __('ui.validation.counterparty.names_required'),
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
     * The template split into pieces, so it can be drawn with one pill per
     * placeholder.
     *
     * @return list<array{placeholder: Placeholder|null, text: string}>
     */
    #[Computed]
    public function templateSegments(): array
    {
        return Placeholder::segments($this->template);
    }

    #[Computed]
    public function typeLetters(): TypeLetters
    {
        return app(AppSettings::class)->typeLetters();
    }

    /** Example name, always as an expense: the income/expense selector lives in Settings. */
    #[Computed]
    public function templateExample(): string
    {
        return TemplateExample::name($this->template, TypeLetter::Expense, $this->typeLetters);
    }

    #[Computed]
    public function downloadUrl(): ?string
    {
        if ($this->okFiles === 0 || $this->runId === '') {
            return null;
        }

        // `cfdi:clean` has already taken `output/`: the run is still in the
        // history, but its ZIP can no longer be built. The archived documents
        // are still there.
        if (Run::query()->whereKey($this->runId)->whereNotNull('files_purged_at')->exists()) {
            return null;
        }

        return UrlGenerator::temporarySignedRoute('runs.download', now()->addHours(2), ['run' => $this->runId]);
    }

    /**
     * The rows of the results table, already persisted.
     *
     * The collection discards come first, exactly like in the command: that
     * order is frozen into `position`.
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
     * Retries the Drive upload of the rows that failed.
     *
     * Only failures are retried, never skips: a CFDI with no issue date has no
     * folder to go to, and asking again would produce the same answer.
     */
    public function retryDrive(DriveSync $sync): void
    {
        $failed = RunFile::query()
            ->with('document')
            ->where('run_id', $this->runId)
            ->where('drive_status', DriveStatus::Failed->value)
            ->get();

        $documents = $failed
            ->filter(fn (RunFile $file): bool => $file->document !== null)
            ->mapWithKeys(fn (RunFile $file): array => [$file->id => $file->document])
            ->all();

        if ($documents === []) {
            return;
        }

        $result = $sync->sync($documents);

        foreach ($result->outcomes as $runFileId => $outcome) {
            RunFile::query()->whereKey($runFileId)->update([
                'drive_status' => $outcome->status->value,
                'drive_link' => $outcome->link,
                'drive_note' => $outcome->reason,
            ]);
        }

        unset($this->allRows);

        Flux::toast(variant: 'success', text: __('ui.drive.retried'));
    }

    /**
     * Whether any row of this run is worth retrying.
     */
    #[Computed]
    public function hasFailedDriveUploads(): bool
    {
        if ($this->runId === '') {
            return false;
        }

        return RunFile::query()
            ->where('run_id', $this->runId)
            ->where('drive_status', DriveStatus::Failed->value)
            ->exists();
    }

    /**
     * Whether the Drive column belongs in the table at all: it does not when
     * the integration was off for this run.
     */
    #[Computed]
    public function showsDriveColumn(): bool
    {
        if ($this->runId === '') {
            return false;
        }

        return RunFile::query()
            ->where('run_id', $this->runId)
            ->whereNotNull('drive_status')
            ->exists();
    }

    /**
     * Signed link to download a single file from the results table.
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
            'files.required' => (string) __('ui.validation.files.required'),
            'files.min' => (string) __('ui.validation.files.required'),
            'files.max' => (string) __('ui.validation.files.max', ['max' => self::MAX_FILES]),
            'files.*.required' => (string) __('ui.validation.files.item_required'),
            'files.*.max' => (string) __('ui.validation.files.item_max'),
        ];
    }

    private function process(
        AppSettings $settings,
        RunStorage $storage,
        FileCollector $collector,
        DocumentArchiver $archiver,
    ): void {
        // Without this, a second submission would produce ` (2)` duplicates on
        // top of the files from the first pass.
        $storage->resetOutput($this->runId);

        $collected = $collector->collect($storage->inputFiles($this->runId));

        $result = app(CfdiProcessor::class)->process(
            $collected->paths,
            new ProcessOptions(
                myRfc: (string) $this->configuredRfc,
                template: $settings->template(),
                outputDirectory: $storage->outputPath($this->runId),
                move: false, // The web always copies: the originals are temporary uploads.
                letters: $settings->typeLetters(),
            ),
            new SuggestedNamePrompt,
        );

        // On the web the archived copy is the only one that will remain: the
        // originals are temporary uploads that `cfdi:clean` deletes.
        $documents = $archiver->archive($result, copyIntoArchive: true);

        // Uploading is the last thing that happens, and it cannot fail the run:
        // the files are already renamed and archived, so a network blip must not
        // undo that. `DriveSync` returns failures as values.
        $drive = app(DriveSync::class)->sync($documents);

        $this->warnings = [...$result->warnings, ...$drive->warnings];
        $this->totalFiles = $result->totalFiles;
        $this->okFiles = $result->okFiles;
        $this->ignoredFiles = count($collected->errors);
        $this->step = self::STEP_RESULTS;

        $this->persist();
        $this->persistFiles($collected->errors, $result, $documents, $drive->outcomes);

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
     * Rewrites the run's rows. A second submission of the form replaces them
     * instead of piling them up.
     *
     * @param  list<CollectionError>  $errors
     * @param  array<int, Document>  $documents
     * @param  array<int, DriveOutcome>  $outcomes  empty when the integration is off
     */
    private function persistFiles(array $errors, ProcessResult $result, array $documents, array $outcomes = []): void
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
                // A discarded file was never placed, so it never had a shot at
                // Drive. The keys still have to be here: `insert()` below is a
                // single bulk statement and every row must carry the same
                // columns.
                'drive_status' => null,
                'drive_link' => null,
                'drive_note' => null,
            ];
        }

        foreach ($result->rows as $index => $row) {
            $outcome = $outcomes[$index] ?? null;

            $rows[] = [
                'run_id' => $this->runId,
                'document_id' => ($documents[$index] ?? null)?->id,
                'position' => $position++,
                'status' => $row->status->value,
                'original_name' => $row->original,
                'new_name' => $row->newName,
                'note' => $row->note,
                'drive_status' => $outcome?->status->value,
                'drive_link' => $outcome?->link,
                'drive_note' => $outcome?->reason,
            ];
        }

        if ($rows !== []) {
            RunFile::query()->insert($rows);
        }
    }

    /**
     * Same name and same size: dropping the same folder twice must not eat two
     * slots of the cap.
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
     * Badge color per status.
     */
    public function statusColor(string $status): string
    {
        return match ($status) {
            FileStatus::Ok->value => 'green',
            FileStatus::Review->value => 'amber',
            default => 'red',
        };
    }

    /**
     * Human label for a raw `run_files.status` value.
     */
    public function statusLabel(string $status): string
    {
        return FileStatus::labelFor($status);
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
