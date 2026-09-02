<div class="mx-auto w-full max-w-5xl space-y-6 p-6">
    <div>
        <flux:heading size="xl">Documentos</flux:heading>
        <flux:subheading>Los CFDIs que ya procesaste, guardados y asignados a su contraparte.</flux:subheading>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            placeholder="Buscar por nombre o folio fiscal"
            class="flex-1"
            clearable
        />

        <flux:select wire:model.live="counterpartyId" placeholder="Todas las contrapartes" class="sm:max-w-56">
            <flux:select.option value="">Todas las contrapartes</flux:select.option>

            @foreach ($this->counterparties as $counterparty)
                <flux:select.option :value="$counterparty->id">{{ $counterparty->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="typeLetter" class="sm:max-w-44">
            <flux:select.option value="">Ingresos y gastos</flux:select.option>

            @foreach ($this->typeLabels as $value => $label)
                <flux:select.option :value="$value">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @if ($this->documents->isEmpty())
        <flux:callout icon="information-circle">
            <flux:callout.text>
                @if ($search !== '' || $counterpartyId !== '' || $typeLetter !== '')
                    Ningún documento coincide con el filtro.
                @else
                    Aún no hay documentos. Se van archivando conforme procesas CFDIs.
                @endif
            </flux:callout.text>
        </flux:callout>
    @else
        <flux:table :paginate="$this->documents">
            <flux:table.columns>
                <flux:table.column>Fecha</flux:table.column>
                <flux:table.column>Nombre</flux:table.column>
                <flux:table.column>Contraparte</flux:table.column>
                <flux:table.column>Tipo</flux:table.column>
                <flux:table.column align="end">Monto</flux:table.column>
                <flux:table.column align="end">Acciones</flux:table.column>
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
                            <flux:badge size="sm" :color="$document->type_letter === 'I' ? 'green' : 'zinc'">
                                {{ $document->type_letter ?? '?' }}
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
                                {{-- Solo pasa con los del comando: el archivo vive en la carpeta
                                     del usuario y lo pudo mover o borrar desde afuera. --}}
                                <flux:badge size="sm" color="amber">Archivo no encontrado</flux:badge>
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

    <flux:modal name="borrar-documento" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">¿Sacarlo del archivo?</flux:heading>
                <flux:subheading>
                    Si la app guardó su propia copia, se borra. Los archivos que solo están
                    indexados desde tu carpeta no se tocan.
                </flux:subheading>
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
