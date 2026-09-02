<?php

namespace App\Livewire;

use App\Cfdi\Naming\NameSanitizer;
use App\Models\Counterparty;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Contrapartes')]
final class ManageCounterparties extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    public ?int $editingId = null;

    public string $editingName = '';

    public ?int $deletingId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function startEdit(int $id): void
    {
        $counterparty = Counterparty::query()->findOrFail($id);

        $this->editingId = $counterparty->id;
        $this->editingName = $counterparty->name;
        $this->resetValidation();

        Flux::modal('editar-contraparte')->show();
    }

    public function saveEdit(NameSanitizer $sanitizer): void
    {
        $this->validate(
            ['editingName' => ['required', 'string', 'max:255']],
            ['editingName.required' => 'Escribe un nombre.'],
        );

        $counterparty = Counterparty::query()->findOrFail($this->editingId);
        $counterparty->update([
            'name' => $sanitizer->sanitize($this->editingName) ?: $counterparty->rfc,
        ]);

        $this->editingId = null;
        $this->editingName = '';

        Flux::modal('editar-contraparte')->close();
        Flux::toast(variant: 'success', text: 'Contraparte actualizada.');
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;

        Flux::modal('borrar-contraparte')->show();
    }

    public function delete(): void
    {
        Counterparty::query()->whereKey($this->deletingId)->delete();

        $this->deletingId = null;

        Flux::modal('borrar-contraparte')->close();
        Flux::toast(text: 'Contraparte eliminada.');
    }

    /**
     * @return LengthAwarePaginator<int, Counterparty>
     */
    #[Computed]
    public function counterparties(): LengthAwarePaginator
    {
        return Counterparty::query()
            ->withCount('documents')
            ->when($this->search !== '', function ($query): void {
                $term = '%'.$this->search.'%';

                $query->where(function ($query) use ($term): void {
                    $query->where('rfc', 'like', $term)
                        ->orWhere('name', 'like', $term)
                        ->orWhere('legal_name', 'like', $term);
                });
            })
            ->orderBy('rfc')
            ->paginate(25);
    }
}
