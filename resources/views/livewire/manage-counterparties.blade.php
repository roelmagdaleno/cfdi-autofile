<div class="mx-auto w-full max-w-6xl space-y-6 p-6">
    <div>
        <flux:heading size="xl">Contrapartes</flux:heading>
        <flux:subheading>Los nombres cortos que se usan al renombrar. Se van guardando conforme procesas CFDIs.</flux:subheading>
    </div>

    <flux:input
        wire:model.live.debounce.300ms="search"
        icon="magnifying-glass"
        placeholder="Buscar por RFC, nombre o razón social"
        clearable
    />

    @if ($this->counterparties->isEmpty())
        <flux:callout icon="information-circle">
            <flux:callout.text>
                @if ($search !== '')
                    Ninguna contraparte coincide con «{{ $search }}».
                @else
                    Aún no hay contrapartes. Se van guardando conforme procesas CFDIs.
                @endif
            </flux:callout.text>
        </flux:callout>
    @else
        <flux:table :paginate="$this->counterparties">
            <flux:table.columns>
                <flux:table.column>RFC</flux:table.column>
                <flux:table.column>Nombre</flux:table.column>
                <flux:table.column>Razón social</flux:table.column>
                <flux:table.column align="end">Documentos</flux:table.column>
                <flux:table.column align="end">Acciones</flux:table.column>
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
                                    :href="route('documents.index', ['contraparte' => $counterparty->id])"
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
                                Renombrar
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

    <flux:modal name="editar-contraparte" class="md:w-96">
        <form wire:submit="saveEdit" class="space-y-6">
            <div>
                <flux:heading size="lg">Renombrar contraparte</flux:heading>
                <flux:subheading>Este nombre es el que aparece en el nombre del archivo.</flux:subheading>
            </div>

            <flux:input wire:model="editingName" label="Nombre" />

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Guardar</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="borrar-contraparte" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">¿Borrar la contraparte?</flux:heading>
                <flux:subheading>Se vuelve a sugerir un nombre la próxima vez que aparezca en un CFDI.</flux:subheading>
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="delete">Borrar</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
