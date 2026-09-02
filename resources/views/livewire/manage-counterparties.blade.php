<div class="mx-auto w-full max-w-6xl space-y-6 p-6">
    <div>
        <flux:heading size="xl">{{ __('ui.counterparties.title') }}</flux:heading>
        <flux:subheading>{{ __('ui.counterparties.subheading') }}</flux:subheading>
    </div>

    <flux:input
        wire:model.live.debounce.300ms="search"
        icon="magnifying-glass"
        :placeholder="__('ui.counterparties.search_placeholder')"
        clearable
    />

    @if ($this->counterparties->isEmpty())
        <flux:callout icon="information-circle">
            <flux:callout.text>
                @if ($search !== '')
                    {{ __('ui.counterparties.no_matches', ['search' => $search]) }}
                @else
                    {{ __('ui.counterparties.empty') }}
                @endif
            </flux:callout.text>
        </flux:callout>
    @else
        <flux:table :paginate="$this->counterparties">
            <flux:table.columns>
                <flux:table.column>{{ __('ui.common.rfc') }}</flux:table.column>
                <flux:table.column>{{ __('ui.common.name') }}</flux:table.column>
                <flux:table.column>{{ __('ui.common.legal_name') }}</flux:table.column>
                <flux:table.column align="end">{{ __('ui.counterparties.documents') }}</flux:table.column>
                <flux:table.column align="end">{{ __('ui.common.actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->counterparties as $counterparty)
                    <flux:table.row :key="$counterparty->id">
                        <flux:table.cell class="font-mono">{{ $counterparty->rfc }}</flux:table.cell>
                        <flux:table.cell variant="strong">{{ $counterparty->name }}</flux:table.cell>
                        <flux:table.cell class="text-zinc-500">{{ $counterparty->legal_name ?? '—' }}</flux:table.cell>
                        <flux:table.cell align="end">
                            @if ($counterparty->documents_count > 0)
                                <flux:link
                                    :href="route('documents.index', ['counterparty' => $counterparty->id])"
                                    variant="subtle"
                                    wire:navigate
                                >
                                    {{ $counterparty->documents_count }}
                                </flux:link>
                            @else
                                <span class="text-zinc-500">—</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="pencil-square"
                                wire:click="startEdit({{ $counterparty->id }})"
                            >
                                {{ __('ui.counterparties.rename') }}
                            </flux:button>

                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="trash"
                                wire:click="confirmDelete({{ $counterparty->id }})"
                            />
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal :name="\App\Livewire\ManageCounterparties::MODAL_EDIT" class="md:w-96">
        <form wire:submit="saveEdit" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('ui.counterparties.rename_heading') }}</flux:heading>
                <flux:subheading>{{ __('ui.counterparties.rename_subheading') }}</flux:subheading>
            </div>

            <flux:input wire:model="editingName" :label="__('ui.common.name')" />

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('ui.common.cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('ui.common.save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal :name="\App\Livewire\ManageCounterparties::MODAL_DELETE" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('ui.counterparties.delete_heading') }}</flux:heading>
                <flux:subheading>{{ __('ui.counterparties.delete_subheading') }}</flux:subheading>
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
