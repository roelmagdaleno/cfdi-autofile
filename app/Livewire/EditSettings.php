<?php

namespace App\Livewire;

use App\Cfdi\Data\TypeLetter;
use App\Cfdi\Naming\Placeholder;
use App\Cfdi\Naming\TemplateExample;
use App\Cfdi\Naming\TypeLetters;
use App\Support\AppSettings;
use Closure;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * @property-read string $preview
 * @property-read TypeLetters $typeLetters
 * @property-read list<Placeholder> $placeholders
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

    public function mount(AppSettings $settings): void
    {
        $this->rfc = $settings->rfc() ?? '';
        $this->template = $settings->template();

        $letters = $settings->typeLetters();
        $this->letterIncome = $letters->income;
        $this->letterExpense = $letters->expense;
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

        Flux::toast(variant: 'success', text: __('ui.settings.saved'));
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
        ];
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
        ];
    }
}
