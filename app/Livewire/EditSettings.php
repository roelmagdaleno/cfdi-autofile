<?php

namespace App\Livewire;

use App\Cfdi\Data\TypeLetter;
use App\Cfdi\Naming\Placeholder;
use App\Cfdi\Naming\TemplateExample;
use App\Support\AppSettings;
use Closure;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

final class EditSettings extends Component
{
    public string $rfc = '';

    public string $template = '';

    /** Type letter used to build the example. It only affects the preview. */
    public string $previewType = TypeLetter::Expense->value;

    public function mount(AppSettings $settings): void
    {
        $this->rfc = $settings->rfc() ?? '';
        $this->template = $settings->template();
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

    public function save(AppSettings $settings): void
    {
        // An RFC pasted with spaces is not a user error. The template is NOT
        // trimmed: it is saved verbatim, just like in the original CLI.
        $this->rfc = trim($this->rfc);

        $validated = $this->validate();

        // The helper does the trim + upper: formatting lives in a single place.
        $settings->setRfc($validated['rfc']);
        $settings->setTemplate($validated['template']);

        $this->rfc = $settings->rfc() ?? '';

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
        ];
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
        ];
    }
}
