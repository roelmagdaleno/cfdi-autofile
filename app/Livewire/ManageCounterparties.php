<?php

namespace App\Livewire;

use App\Cfdi\Naming\NameSanitizer;
use App\Models\Counterparty;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

final class ManageCounterparties extends Component
{
    use WithPagination;

    public const string MODAL_EDIT = 'edit-counterparty';

    public const string MODAL_DELETE = 'delete-counterparty';

    #[Url(except: '')]
    public string $search = '';

    public ?int $editingId = null;

    public string $editingName = '';

    public ?int $deletingId = null;

    /**
     * The page title is set here rather than with `#[Title]`: attribute
     * arguments must be constant, and this one is translated.
     */
    public function render(): View
    {
        return view('livewire.manage-counterparties')->title(__('ui.counterparties.title'));
    }

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

        Flux::modal(self::MODAL_EDIT)->show();
    }

    public function saveEdit(NameSanitizer $sanitizer): void
    {
        $this->validate(
            ['editingName' => ['required', 'string', 'max:255']],
            ['editingName.required' => (string) __('ui.validation.counterparty.name_required')],
        );

        $counterparty = Counterparty::query()->findOrFail($this->editingId);
        $counterparty->update([
            'name' => $sanitizer->sanitize($this->editingName) ?: $counterparty->rfc,
        ]);

        $this->editingId = null;
        $this->editingName = '';

        Flux::modal(self::MODAL_EDIT)->close();
        Flux::toast(variant: 'success', text: __('ui.counterparties.updated'));
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;

        Flux::modal(self::MODAL_DELETE)->show();
    }

    public function delete(): void
    {
        Counterparty::query()->whereKey($this->deletingId)->delete();

        $this->deletingId = null;

        Flux::modal(self::MODAL_DELETE)->close();
        Flux::toast(text: __('ui.counterparties.deleted'));
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
