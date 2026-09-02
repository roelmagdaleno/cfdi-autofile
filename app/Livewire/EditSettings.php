<?php

namespace App\Livewire;

use App\Cfdi\Data\TypeLetter;
use App\Cfdi\Naming\Placeholder;
use App\Cfdi\Naming\TemplateExample;
use App\Support\AppSettings;
use Closure;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Ajustes')]
final class EditSettings extends Component
{
    public string $rfc = '';

    public string $template = '';

    /** Letra de tipo con la que se arma el ejemplo. Solo afecta al preview. */
    public string $previewType = TypeLetter::Expense->value;

    public function mount(AppSettings $settings): void
    {
        $this->rfc = $settings->rfc() ?? '';
        $this->template = $settings->template();
    }

    /**
     * Mete el placeholder en la plantilla, en la seleccion que traiga el input.
     *
     * El texto se inserta tal cual, sin agregar espacios: lo que se ve en el
     * ejemplo de abajo es exactamente lo que quedo escrito.
     *
     * @param  int|null  $start  Inicio de la seleccion; al final si no hay foco.
     * @param  int|null  $end  Fin de la seleccion, para reemplazar lo marcado.
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
        // Un RFC pegado con espacios no es un error del usuario. La plantilla NO
        // se recorta: se guarda tal cual, igual que en el CLI original.
        $this->rfc = trim($this->rfc);

        $validated = $this->validate();

        // El helper hace el trim + upper: el formateo vive en un solo lugar.
        $settings->setRfc($validated['rfc']);
        $settings->setTemplate($validated['template']);

        $this->rfc = $settings->rfc() ?? '';

        Flux::toast(variant: 'success', text: 'Ajustes guardados.');
    }

    /**
     * Nombre de ejemplo con la plantilla actual. Sirve de documentacion viva de
     * la regla "un placeholder desconocido se deja literal".
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
                    $fail('La plantilla debe incluir al menos un placeholder, por ejemplo {fecha}.');
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
            'rfc.required' => 'Escribe tu RFC.',
            'rfc.min' => 'El RFC debe tener 12 o 13 caracteres.',
            'rfc.max' => 'El RFC debe tener 12 o 13 caracteres.',
            'rfc.regex' => 'El RFC no tiene un formato valido.',
            'template.required' => 'Escribe una plantilla.',
            'template.max' => 'La plantilla es demasiado larga.',
        ];
    }
}
