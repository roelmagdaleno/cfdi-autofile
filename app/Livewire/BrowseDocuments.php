<?php

namespace App\Livewire;

use App\Cfdi\Data\TypeLetter;
use App\Models\Counterparty;
use App\Models\Document;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL as UrlGenerator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Documentos')]
final class BrowseDocuments extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(as: 'contraparte', except: '')]
    public string $counterpartyId = '';

    #[Url(as: 'tipo', except: '')]
    public string $typeLetter = '';

    public ?int $deletingId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCounterpartyId(): void
    {
        $this->resetPage();
    }

    public function updatedTypeLetter(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;

        Flux::modal('borrar-documento')->show();
    }

    /**
     * Saca el documento del archivo.
     *
     * El archivo en disco solo se borra cuando es una copia nuestra. Los del
     * comando viven en la carpeta del usuario y no se tocan: la app nada mas
     * deja de indexarlos.
     */
    public function delete(): void
    {
        $document = Document::query()->find($this->deletingId);

        if ($document !== null) {
            if ($document->isArchived()) {
                Storage::disk((string) $document->disk)->delete($document->path);
            }

            $document->delete();
        }

        $this->deletingId = null;

        Flux::modal('borrar-documento')->close();
        Flux::toast(text: 'Documento eliminado del archivo.');
    }

    /**
     * @return LengthAwarePaginator<int, Document>
     */
    #[Computed]
    public function documents(): LengthAwarePaginator
    {
        return Document::query()
            ->with('counterparty')
            ->when($this->search !== '', function ($query): void {
                $term = '%'.$this->search.'%';

                $query->where(function ($query) use ($term): void {
                    $query->where('name', 'like', $term)
                        ->orWhere('original_name', 'like', $term)
                        ->orWhere('folio', 'like', $term);
                });
            })
            ->when($this->counterpartyId !== '', fn ($query) => $query->where('counterparty_id', $this->counterpartyId))
            ->when($this->typeLetter !== '', fn ($query) => $query->where('type_letter', $this->typeLetter))
            ->orderByDesc('issued_on')
            ->orderByDesc('id')
            ->paginate(25);
    }

    /**
     * Solo las contrapartes que de verdad tienen documentos: un selector con
     * opciones que no filtran nada no le sirve a nadie.
     *
     * @return Collection<int, Counterparty>
     */
    #[Computed]
    public function counterparties(): Collection
    {
        return Counterparty::query()
            ->whereHas('documents')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function typeLabels(): array
    {
        return [
            TypeLetter::Expense->value => 'Gastos (G)',
            TypeLetter::Income->value => 'Ingresos (I)',
            TypeLetter::Unknown->value => 'Sin determinar (?)',
        ];
    }

    public function downloadUrl(Document $document): ?string
    {
        if (! $document->fileExists()) {
            return null;
        }

        return UrlGenerator::temporarySignedRoute(
            'documents.download',
            now()->addHours(2),
            ['document' => $document->id],
        );
    }
}
