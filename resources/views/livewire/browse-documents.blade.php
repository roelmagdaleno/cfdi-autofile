<div class="mx-auto w-full max-w-5xl space-y-6 p-6">
    <div>
        <flux:heading size="xl">{{ __('ui.documents.title') }}</flux:heading>
        <flux:subheading>{{ __('ui.documents.subheading') }}</flux:subheading>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            :placeholder="__('ui.documents.search_placeholder')"
            class="flex-1"
            clearable
        />

        <flux:select wire:model.live="counterpartyId" :placeholder="__('ui.documents.all_counterparties')" class="sm:max-w-56">
            <flux:select.option value="">{{ __('ui.documents.all_counterparties') }}</flux:select.option>

            @foreach ($this->counterparties as $counterparty)
                <flux:select.option :value="$counterparty->id">{{ $counterparty->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="typeLetter" class="sm:max-w-44">
            <flux:select.option value="">{{ __('ui.documents.all_types') }}</flux:select.option>

            @foreach ($this->typeLabels as $value => $label)
                <flux:select.option :value="$value">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @if ($this->documents->isEmpty())
        <flux:callout icon="information-circle">
            <flux:callout.text>
                @if ($search !== '' || $counterpartyId !== '' || $typeLetter !== '')
                    {{ __('ui.documents.no_matches') }}
                @else
                    {{ __('ui.documents.empty') }}
                @endif
            </flux:callout.text>
        </flux:callout>
    @else
        <flux:table :paginate="$this->documents">
            <flux:table.columns>
                <flux:table.column>{{ __('ui.common.date') }}</flux:table.column>
                <flux:table.column>{{ __('ui.common.name') }}</flux:table.column>
                <flux:table.column>{{ __('ui.common.counterparty') }}</flux:table.column>
                <flux:table.column>{{ __('ui.common.type') }}</flux:table.column>
                <flux:table.column align="end">{{ __('ui.common.amount') }}</flux:table.column>
                <flux:table.column align="end">{{ __('ui.common.actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->documents as $document)
                    <flux:table.row :key="$document->id">
                        <flux:table.cell class="whitespace-nowrap">
                            {{ $document->issued_on?->format('Y-m-d') ?? '—' }}
                        </flux:table.cell>

                        <flux:table.cell variant="strong" class="max-w-xs whitespace-normal break-words">
                            <span class="font-mono text-xs">{{ $document->name }}</span>

                            @if ($document->folio !== null)
                                <span class="mt-1 block font-mono text-xs text-zinc-500">{{ $document->folio }}</span>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>{{ $document->counterparty?->name ?? '—' }}</flux:table.cell>

                        <flux:table.cell>
                            <flux:badge size="sm" :color="$document->type_letter === \App\Cfdi\Data\TypeLetter::Income->value ? 'green' : 'zinc'">
                                {{ $this->displayLetter($document->type_letter) }}
                            </flux:badge>
                        </flux:table.cell>

                        <flux:table.cell align="end" class="font-mono whitespace-nowrap">
                            {{ $document->total ?? '—' }}
                        </flux:table.cell>

                        <flux:table.cell align="end">
                            @if ($url = $this->downloadUrl($document))
                                <flux:button :href="$url" size="sm" variant="ghost" icon="arrow-down-tray">
                                    {{ strtoupper($document->extension) }}
                                </flux:button>
                            @else
                                {{-- Only happens with the ones from the command: the file lives in
                                     the user's folder and may have been moved or deleted from
                                     outside. --}}
                                <flux:badge size="sm" color="amber">{{ __('ui.documents.file_not_found') }}</flux:badge>
                            @endif

                            @if ($document->isOnDrive())
                                <flux:tooltip :content="__('ui.drive.open')">
                                    <flux:button
                                        :href="$document->drive_link"
                                        target="_blank"
                                        size="sm"
                                        variant="ghost"
                                        icon="cloud"
                                    />
                                </flux:tooltip>
                            @endif

                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="trash"
                                wire:click="confirmDelete({{ $document->id }})"
                            />
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal :name="\App\Livewire\BrowseDocuments::MODAL_DELETE" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('ui.documents.delete_heading') }}</flux:heading>
                <flux:subheading>{{ __('ui.documents.delete_subheading') }}</flux:subheading>
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('ui.common.cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="delete">{{ __('ui.common.delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
