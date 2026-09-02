<?php

namespace App\Livewire;

use App\Cfdi\Data\TypeLetter;
use App\Cfdi\Naming\TypeLetters;
use App\Models\Counterparty;
use App\Models\Document;
use App\Support\AppSettings;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL as UrlGenerator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * @property-read LengthAwarePaginator<int, Document> $documents
 * @property-read Collection<int, Counterparty> $counterparties
 * @property-read array<string, string> $typeLabels
 * @property-read TypeLetters $typeLetters
 */
final class BrowseDocuments extends Component
{
    use WithPagination;

    public const string MODAL_DELETE = 'delete-document';

    #[Url(except: '')]
    public string $search = '';

    #[Url(as: 'counterparty', except: '')]
    public string $counterpartyId = '';

    #[Url(as: 'type', except: '')]
    public string $typeLetter = '';

    public ?int $deletingId = null;

    /**
     * The page title is set here rather than with `#[Title]`: attribute
     * arguments must be constant, and this one is translated.
     */
    public function render(): View
    {
        return view('livewire.browse-documents')->title(__('ui.documents.title'));
    }

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

        Flux::modal(self::MODAL_DELETE)->show();
    }

    /**
     * Removes the document from the archive.
     *
     * The file on disk is only deleted when it is a copy of ours. The ones from
     * the command live in the user's folder and are left untouched: the app
     * simply stops indexing them.
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

        Flux::modal(self::MODAL_DELETE)->close();
        Flux::toast(text: __('ui.documents.deleted'));
    }

    /**
     * @return LengthAwarePaginator<int, Document>
     */
    #[Computed]
    public function documents(): LengthAwarePaginator
    {
        return Document::query()
            ->with(['counterparty', 'cloudFiles'])
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
     * Only the counterparties that actually have documents: a selector with
     * options that filter nothing is useless.
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
     * The filter options, keyed by the canonical value stored in
     * `documents.type_letter`.
     *
     * The key is what the query compares against and never changes; only the
     * label follows the configured letters, so renaming them does not hide the
     * documents archived before the change.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function typeLabels(): array
    {
        return [
            TypeLetter::Expense->value => (string) __('ui.documents.type.expense', ['letter' => $this->typeLetters->expense]),
            TypeLetter::Income->value => (string) __('ui.documents.type.income', ['letter' => $this->typeLetters->income]),
            TypeLetter::Unknown->value => (string) __('ui.documents.type.unknown'),
        ];
    }

    #[Computed]
    public function typeLetters(): TypeLetters
    {
        return app(AppSettings::class)->typeLetters();
    }

    /**
     * The letter drawn on a row's badge.
     *
     * A row stores the canonical value, so an unrecognized one (or `null`) is
     * treated as undetermined.
     */
    public function displayLetter(?string $canonical): string
    {
        return $this->typeLetters->for(
            TypeLetter::tryFrom((string) $canonical) ?? TypeLetter::Unknown,
        );
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
