<?php

namespace App\Livewire;

use App\Cfdi\Data\TypeLetter;
use App\Cfdi\Naming\Placeholder;
use App\Cfdi\Naming\TemplateExample;
use App\Cfdi\Naming\TypeLetters;
use App\Cloud\CloudAccount;
use App\Cloud\Contracts\CloudFiles;
use App\Cloud\Exceptions\CloudException;
use App\Cloud\Google\GoogleDestinations;
use App\Cloud\Google\GoogleDriveConfig;
use App\Cloud\Google\GoogleFolderTemplate;
use App\Support\AppSettings;
use Closure;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * @property-read string $preview
 * @property-read TypeLetters $typeLetters
 * @property-read list<Placeholder> $placeholders
 * @property-read array{income: string, expense: string} $drivePreview
 */
final class EditSettings extends Component
{
    public string $rfc = '';

    public string $template = '';

    /** What `{type}` writes for income. */
    public string $letterIncome = '';

    /** What `{type}` writes for an expense. */
    public string $letterExpense = '';

    /** Type letter used to build the example. It only affects the preview. */
    public string $previewType = TypeLetter::Expense->value;

    public bool $driveEnabled = false;

    /** Empty means the top of "My Drive". */
    public string $driveRootFolderId = '';

    public string $driveIncomeFolder = '';

    public string $driveExpenseFolder = '';

    /**
     * Read once on mount rather than through a computed property: the OAuth
     * flow leaves the page and comes back, so a fresh mount is the only moment
     * this can change.
     */
    public bool $driveConnected = false;

    public string $driveEmail = '';

    public function mount(AppSettings $settings): void
    {
        $this->rfc = $settings->rfc() ?? '';
        $this->template = $settings->template();

        $letters = $settings->typeLetters();
        $this->letterIncome = $letters->income;
        $this->letterExpense = $letters->expense;

        $this->hydrateDrive($settings);
    }

    /**
     * The page title is set here rather than with `#[Title]`: attribute
     * arguments must be constant, and this one is translated.
     */
    public function render(): View
    {
        return view('livewire.edit-settings')->title(__('ui.settings.title'));
    }

    /**
     * Inserts the placeholder into the template, at whatever selection the input
     * carries.
     *
     * The text is inserted verbatim, without adding spaces: what the example
     * below shows is exactly what was written.
     *
     * @param  int|null  $start  Start of the selection; the end when unfocused.
     * @param  int|null  $end  End of the selection, to replace what is highlighted.
     */
    public function insertPlaceholder(string $placeholder, ?int $start = null, ?int $end = null): void
    {
        $known = Placeholder::tryFrom($placeholder);

        if (! $known instanceof Placeholder) {
            return;
        }

        $length = mb_strlen($this->template);
        $start = $start === null ? $length : max(0, min($start, $length));
        $end = $end === null ? $start : max($start, min($end, $length));

        $this->template = mb_substr($this->template, 0, $start)
            .$known->token()
            .mb_substr($this->template, $end);
    }

    public function resetTemplate(): void
    {
        $this->template = AppSettings::DEFAULT_TEMPLATE;
    }

    /**
     * The pair currently typed on screen, saved or not.
     *
     * The example and the `{type}` tooltip read from here, so both react while
     * the user is still editing the labels.
     */
    #[Computed]
    public function typeLetters(): TypeLetters
    {
        return new TypeLetters(
            income: trim($this->letterIncome),
            expense: trim($this->letterExpense),
        );
    }

    public function save(AppSettings $settings): void
    {
        // An RFC pasted with spaces is not a user error. The template is NOT
        // trimmed: it is saved verbatim, just like in the original CLI.
        $this->rfc = trim($this->rfc);

        $validated = $this->validate();

        // The helper does the trim + upper: formatting lives in a single place.
        $settings->setRfc($validated['rfc']);
        $settings->setTemplate($validated['template']);
        $settings->setTypeLetters(new TypeLetters(
            income: $validated['letterIncome'],
            expense: $validated['letterExpense'],
        ));

        $this->rfc = $settings->rfc() ?? '';

        $letters = $settings->typeLetters();
        $this->letterIncome = $letters->income;
        $this->letterExpense = $letters->expense;

        $settings->setDrive($this->driveConfig());
        $this->hydrateDrive($settings);

        Flux::toast(variant: 'success', text: __('ui.settings.saved'));
    }

    /**
     * Forgets the authorization without touching the folder configuration, so
     * reconnecting does not mean setting the templates up again.
     */
    public function disconnectDrive(AppSettings $settings): void
    {
        $settings->disconnectDrive();

        $this->hydrateDrive($settings);

        Flux::toast(variant: 'success', text: __('ui.drive.disconnected'));
    }

    /**
     * Resolves this year's two folders against Drive, creating them if needed.
     *
     * It runs against what is TYPED, not what is saved, so it answers the only
     * question that matters before the first run: is this root folder id right,
     * and are these the folders I already use?
     */
    public function verifyDrive(AppSettings $settings, CloudFiles $files): void
    {
        if (! $settings->isDriveConnected()) {
            Flux::toast(variant: 'danger', text: __('ui.validation.drive.not_connected'));

            return;
        }

        $this->validate([
            'driveIncomeFolder' => $this->driveFolderRules(),
            'driveExpenseFolder' => $this->driveFolderRules(),
        ]);

        $resolver = new GoogleDestinations($files, $this->driveConfig());
        $year = (int) now()->format('Y');

        try {
            $resolver->folderIdFor(TypeLetter::Income, "{$year}-01-01");
            $resolver->folderIdFor(TypeLetter::Expense, "{$year}-01-01");
        } catch (CloudException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('ui.drive.verified', $this->drivePreview));
    }

    /**
     * The two folder names the current form values would produce this year.
     *
     * @return array{income: string, expense: string}
     */
    #[Computed]
    public function drivePreview(): array
    {
        $year = (int) now()->format('Y');

        return [
            'income' => (new GoogleFolderTemplate(trim($this->driveIncomeFolder)))->render($year),
            'expense' => (new GoogleFolderTemplate(trim($this->driveExpenseFolder)))->render($year),
        ];
    }

    private function hydrateDrive(AppSettings $settings): void
    {
        $drive = $settings->drive();

        $this->driveEnabled = $drive->enabled;
        // The default alias is an implementation detail: an empty box reads as
        // "the top of My Drive", which is what it means.
        $this->driveRootFolderId = $drive->rootFolderId === GoogleDriveConfig::ROOT ? '' : $drive->rootFolderId;
        $this->driveIncomeFolder = $drive->incomeFolder;
        $this->driveExpenseFolder = $drive->expenseFolder;
        $this->driveConnected = $settings->isDriveConnected();
        // Resolved through `CloudAccount` rather than read straight from the
        // settings: an install whose address never made it in gets it filled on
        // this visit instead of showing "connected as" and nothing.
        $this->driveEmail = app(CloudAccount::class)->email() ?? '';
    }

    private function driveConfig(): GoogleDriveConfig
    {
        $root = trim($this->driveRootFolderId);

        return new GoogleDriveConfig(
            enabled: $this->driveEnabled,
            rootFolderId: $root === '' ? GoogleDriveConfig::ROOT : $root,
            incomeFolder: trim($this->driveIncomeFolder),
            expenseFolder: trim($this->driveExpenseFolder),
        );
    }

    /**
     * Example name built with the current template. It doubles as living
     * documentation of the "an unknown placeholder is left literal" rule.
     */
    #[Computed]
    public function preview(): string
    {
        return TemplateExample::name(
            $this->template,
            TypeLetter::tryFrom($this->previewType) ?? TypeLetter::Expense,
            $this->typeLetters,
        );
    }

    /**
     * @return list<Placeholder>
     */
    #[Computed]
    public function placeholders(): array
    {
        return Placeholder::cases();
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'rfc' => ['required', 'string', 'min:12', 'max:13', 'regex:/^[A-Za-zÑñ&]{3,4}[0-9]{6}[A-Za-z0-9]{3}$/u'],
            'template' => ['required', 'string', 'max:255', function (string $attribute, mixed $value, Closure $fail): void {
                if (preg_match(Placeholder::pattern(), is_string($value) ? $value : '') !== 1) {
                    $fail((string) __('ui.validation.template.placeholder', [
                        'token' => Placeholder::Date->token(),
                    ]));
                }
            }],
            'letterIncome' => $this->letterRules(),
            'letterExpense' => $this->letterRules(),
            'driveEnabled' => ['boolean', function (string $attribute, mixed $value, Closure $fail): void {
                // Turning the switch on without an authorized account would
                // make every run fail identically, twenty rows at a time.
                if ($value === true && ! $this->driveConnected) {
                    $fail((string) __('ui.validation.drive.not_connected'));
                }
            }],
            'driveRootFolderId' => ['nullable', 'string', 'max:255'],
            'driveIncomeFolder' => $this->driveFolderRules(),
            'driveExpenseFolder' => $this->driveFolderRules(),
        ];
    }

    /**
     * The folder names are only enforced while the integration is on, so
     * turning it off is never blocked by them.
     *
     * `requiredIf` rather than a check inside the closure: Laravel skips
     * non-implicit rules on an empty value, so a closure alone never sees the
     * blank field it is supposed to reject.
     *
     * @return array<int, mixed>
     */
    private function driveFolderRules(): array
    {
        return [Rule::requiredIf(fn (): bool => $this->driveEnabled), 'string', function (string $attribute, mixed $value, Closure $fail): void {
            if (! $this->driveEnabled) {
                return;
            }

            $template = trim(is_string($value) ? $value : '');

            if (mb_strlen($template) > GoogleFolderTemplate::MAX_LENGTH) {
                $fail((string) __('ui.validation.drive.folder_max', ['max' => GoogleFolderTemplate::MAX_LENGTH]));

                return;
            }

            if (! str_contains($template, GoogleFolderTemplate::TOKEN)) {
                $fail((string) __('ui.validation.drive.folder_token', ['token' => GoogleFolderTemplate::TOKEN]));

                return;
            }

            if (! GoogleFolderTemplate::isValid($template)) {
                $fail((string) __('ui.validation.drive.folder_format'));
            }
        }];
    }

    /**
     * The label is trimmed before saving, so the rules run against the trimmed
     * value: a single space is `required`-empty, not a one-character label.
     *
     * @return array<int, mixed>
     */
    private function letterRules(): array
    {
        return ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
            $letter = trim(is_string($value) ? $value : '');

            if ($letter === '') {
                $fail((string) __('ui.validation.letter.required'));

                return;
            }

            if (mb_strlen($letter) > TypeLetters::MAX_LENGTH) {
                $fail((string) __('ui.validation.letter.max', ['max' => TypeLetters::MAX_LENGTH]));

                return;
            }

            if (! TypeLetters::isValid($letter)) {
                $fail((string) __('ui.validation.letter.format'));
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'rfc.required' => (string) __('ui.validation.rfc.required'),
            'rfc.min' => (string) __('ui.validation.rfc.size'),
            'rfc.max' => (string) __('ui.validation.rfc.size'),
            'rfc.regex' => (string) __('ui.validation.rfc.format'),
            'template.required' => (string) __('ui.validation.template.required'),
            'template.max' => (string) __('ui.validation.template.max'),
            'letterIncome.required' => (string) __('ui.validation.letter.required'),
            'letterExpense.required' => (string) __('ui.validation.letter.required'),
            'driveIncomeFolder.required' => (string) __('ui.validation.drive.folder_required'),
            'driveExpenseFolder.required' => (string) __('ui.validation.drive.folder_required'),
        ];
    }
}
