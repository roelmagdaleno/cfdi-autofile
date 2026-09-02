<div
    class="mx-auto w-full max-w-6xl space-y-6 p-6"
    x-data="{
        dragging: 0,
        uploading: false,
        progress: 0,
        maxFiles: @js(\App\Livewire\RunCfdis::MAX_FILES),
        extensions: ['pdf', 'xml'],

        get accepting() {
            // Se lee del componente y no del render: x-data no se reevalua cuando Livewire
            // remorfea, asi que un valor horneado se quedaria congelado.
            return $wire.step === @js(\App\Livewire\RunCfdis::STEP_UPLOAD)
                && @js($this->configuredRfc !== null)
                && ! this.uploading;
        },

        /** Un drag de texto o de un enlace no debe encender el overlay. */
        carriesFiles(event) {
            return Array.from(event.dataTransfer?.types ?? []).includes('Files');
        },

        enter(event) {
            if (this.accepting && this.carriesFiles(event)) {
                this.dragging++;
            }
        },

        leave() {
            if (this.dragging > 0) {
                this.dragging--;
            }
        },

        drop(event) {
            this.dragging = 0;

            if (! this.accepting || ! this.carriesFiles(event)) {
                return;
            }

            // `items` deja de ser valido cuando el handler regresa, asi que los
            // entries se toman de forma sincrona antes de cualquier await.
            const entries = Array.from(event.dataTransfer.items ?? [])
                .map(item => item.webkitGetAsEntry?.())
                .filter(Boolean);

            if (entries.length === 0) {
                this.send(Array.from(event.dataTransfer.files).filter(file => this.supported(file.name)));

                return;
            }

            this.gather(entries).then(files => this.send(files));
        },

        async gather(entries) {
            const files = [];

            for (const entry of entries) {
                await this.walk(entry, files);
            }

            return files;
        },

        /** Recorre carpetas para que soltar la descarga del SAT tal cual funcione. */
        async walk(entry, files) {
            if (entry.isFile) {
                if (this.supported(entry.name)) {
                    files.push(await new Promise((resolve, reject) => entry.file(resolve, reject)));
                }

                return;
            }

            const reader = entry.createReader();

            // `readEntries` entrega por tandas: hay que llamarlo hasta que devuelva vacio.
            while (true) {
                const batch = await new Promise((resolve, reject) => reader.readEntries(resolve, reject));

                if (batch.length === 0) {
                    break;
                }

                for (const child of batch) {
                    await this.walk(child, files);
                }
            }
        },

        supported(name) {
            return this.extensions.includes(name.split('.').pop()?.toLowerCase());
        },

        send(files) {
            if (files.length === 0) {
                return;
            }

            this.uploading = true;
            this.progress = 0;

            const done = () => {
                this.uploading = false;
                this.progress = 0;
            };

            // El recorte evita que `max_file_uploads` de PHP trunque en silencio.
            $wire.uploadMultiple('incomingFiles', files.slice(0, this.maxFiles), done, done, event => {
                this.progress = event.detail.progress;
            });
        },
    }"
    x-on:dragenter.window.prevent="enter($event)"
    x-on:dragover.window.prevent
    x-on:dragleave.window.prevent="leave()"
    x-on:dragend.window="dragging = 0"
    x-on:drop.window.prevent="drop($event)"
>
    <div>
        <flux:heading size="xl">Procesar CFDIs</flux:heading>
        <flux:subheading>Sube los PDF y XML. El XML manda; el PDF es sólo respaldo.</flux:subheading>
    </div>

    @if ($step === 'upload')
        @if ($this->configuredRfc === null)
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.heading>Falta configurar tu RFC</flux:callout.heading>
                <flux:callout.text>
                    Sin tu RFC no se puede saber quién es la contraparte ni si cada CFDI es un ingreso o un gasto.
                </flux:callout.text>
                <x-slot:actions>
                    <flux:button :href="route('settings.edit')" wire:navigate size="sm">Ir a Ajustes</flux:button>
                </x-slot:actions>
            </flux:callout>
        @else
            <flux:card variant="soft" size="sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <flux:icon.identification class="size-5 shrink-0 text-zinc-400" />

                        <div>
                            <flux:text variant="subtle" class="text-xs">Tu RFC</flux:text>
                            <flux:heading size="sm" class="font-mono">{{ $this->configuredRfc }}</flux:heading>
                        </div>
                    </div>

                    <flux:button
                        :href="route('settings.edit')"
                        wire:navigate
                        size="sm"
                        variant="subtle"
                        icon="cog-6-tooth"
                    >
                        Cambiar
                    </flux:button>
                </div>

                <flux:separator variant="subtle" class="my-3" />

                <flux:text variant="subtle" class="text-xs">Así se van a llamar tus archivos</flux:text>

                {{--
                    Cada pieza conocida sale como pill con su explicación en el
                    tooltip; el texto de en medio conserva sus espacios con
                    `whitespace-pre` para que la plantilla se lea tal cual está
                    escrita.
                --}}
                <div class="mt-2.5 flex flex-wrap items-center gap-x-0.2 gap-y-1.5 font-mono text-sm">
                    @foreach ($this->templateSegments as $segment)
                        @if ($segment['placeholder'] !== null)
                            <flux:tooltip :content="$segment['placeholder']->description()">
                                <span
                                    tabindex="0"
                                    class="rounded-md bg-zinc-200 px-1.5 py-0.5 text-xs text-zinc-800 ring-1 ring-zinc-950/[0.08] dark:bg-white/15 dark:text-zinc-100 dark:ring-white/10"
                                >
                                    {{ $segment['placeholder']->value }}
                                </span>
                            </flux:tooltip>
                        @else
                            <span class="whitespace-pre text-zinc-500 dark:text-zinc-400">{{ $segment['text'] }}</span>
                        @endif
                    @endforeach
                </div>

                <flux:text variant="subtle" class="mt-3.5 text-xs break-all">
                    Ejemplo: <span class="font-mono">{{ $this->templateExample }}</span>
                </flux:text>
            </flux:card>
        @endif

        <form wire:submit="analyze" class="space-y-4">
            <div
                class="rounded-xl border-2 border-dashed p-8 text-center transition-colors"
                :class="dragging > 0
                    ? 'border-zinc-400 bg-zinc-50 dark:border-zinc-400 dark:bg-zinc-700/50'
                    : 'border-zinc-300 dark:border-zinc-600'"
            >
                <flux:icon.arrow-up-tray class="mx-auto size-8 text-zinc-400" />

                <flux:heading class="mt-3">Arrastra tus PDF y XML aquí</flux:heading>

                <flux:text class="mt-1">
                    Suéltalos en cualquier parte de la pantalla; también sirve una carpeta completa.
                </flux:text>

                <flux:button
                    type="button"
                    variant="filled"
                    size="sm"
                    class="mt-4"
                    x-on:click="$refs.picker.click()"
                    :disabled="$this->configuredRfc === null"
                >
                    Selecciona archivos
                </flux:button>

                <flux:text variant="subtle" class="mt-3">
                    Hasta {{ \App\Livewire\RunCfdis::MAX_FILES }} archivos, 20 MB cada uno.
                </flux:text>

                <input
                    type="file"
                    class="sr-only"
                    x-ref="picker"
                    wire:model="incomingFiles"
                    accept=".pdf,.xml"
                    multiple
                    @disabled($this->configuredRfc === null)
                    x-on:livewire-upload-start="uploading = true; progress = 0"
                    x-on:livewire-upload-progress="progress = $event.detail.progress"
                    x-on:livewire-upload-finish="uploading = false; progress = 0; $el.value = ''"
                    x-on:livewire-upload-error="uploading = false; progress = 0; $el.value = ''"
                />
            </div>

            <div x-show="uploading" style="display: none" class="space-y-1">
                <div class="h-1.5 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                    <div class="h-full bg-zinc-500 transition-all dark:bg-zinc-400" :style="`width: ${progress}%`"></div>
                </div>

                <flux:text variant="subtle">Subiendo… <span x-text="progress"></span>%</flux:text>
            </div>

            @if ($files !== [])
                <div class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                    @foreach ($files as $index => $file)
                        <div class="flex items-center gap-3 px-3 py-2" wire:key="file-{{ $file->getFilename() }}">
                            <flux:icon.document class="size-4 shrink-0 text-zinc-400" />

                            <span class="min-w-0 flex-1 truncate font-mono text-xs">{{ $file->getClientOriginalName() }}</span>

                            <flux:text variant="subtle" class="shrink-0 text-xs">
                                {{ $file->getSize() < 1024 ? $file->getSize().' B' : number_format($file->getSize() / 1024).' KB' }}
                            </flux:text>

                            <flux:button
                                type="button"
                                size="xs"
                                variant="subtle"
                                icon="x-mark"
                                wire:click="removeFile({{ $index }})"
                            />
                        </div>
                    @endforeach
                </div>
            @endif

            <flux:error name="incomingFiles" />
            <flux:error name="incomingFiles.*" />
            <flux:error name="files" />
            <flux:error name="files.*" />

            <flux:button
                type="submit"
                variant="primary"
                icon="arrow-up-tray"
                x-bind:disabled="uploading || @js($this->configuredRfc === null)"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove wire:target="analyze">Procesar</span>
                <span wire:loading wire:target="analyze">Procesando…</span>
            </flux:button>
        </form>

        <div
            x-show="dragging > 0"
            style="display: none"
            class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-900/50 p-6 backdrop-blur-sm"
        >
            <div class="pointer-events-none rounded-2xl border-2 border-dashed border-white/60 px-12 py-10 text-center">
                <flux:icon.arrow-up-tray class="mx-auto size-10 text-white" />

                <p class="mt-4 text-lg font-medium text-white">Suelta tus PDF y XML aquí</p>

                <p class="mt-1 text-sm text-white/70">Se agregan a los que ya tienes seleccionados.</p>
            </div>
        </div>
    @endif

    @if ($step === 'counterparties')
        <div>
            <flux:heading size="lg">Contrapartes nuevas</flux:heading>
            <flux:subheading>
                Estos RFC aparecen por primera vez. El nombre propuesto sale de la razón social; edítalo si quieres.
            </flux:subheading>
        </div>

        <form wire:submit="confirmCounterparties" class="space-y-4">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>RFC</flux:table.column>
                    <flux:table.column>Razón social</flux:table.column>
                    <flux:table.column>Nombre a usar</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($newCounterparties as $index => $counterparty)
                        <flux:table.row :key="$counterparty['rfc']">
                            <flux:table.cell class="font-mono">{{ $counterparty['rfc'] }}</flux:table.cell>
                            <flux:table.cell class="text-zinc-500">{{ $counterparty['legalName'] ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:input wire:model="newCounterparties.{{ $index }}.name" size="sm" />
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <flux:error name="newCounterparties.*.name" />

            <div class="flex gap-2">
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    Guardar y procesar
                </flux:button>

                <flux:button variant="ghost" wire:click="startOver" type="button">Cancelar</flux:button>
            </div>
        </form>
    @endif

    @if ($step === 'results')
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Estado</flux:table.column>
                <flux:table.column>Original</flux:table.column>
                <flux:table.column>Nuevo nombre</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->allRows as $row)
                    <flux:table.row>
                        <flux:table.cell>
                            <flux:badge
                                size="sm"
                                :color="$this->statusColor($row->status)"
                                :icon="$this->statusIcon($row->status)"
                            >
                                {{ $row->status }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="max-w-xs whitespace-normal break-words">
                            <span class="font-mono text-xs">{{ $row->original_name }}</span>

                            {{-- El motivo solo existe en los rechazados: en los demas la nota
                                 diria "copiado" en todas las filas y no aportaria nada. --}}
                            @if ($this->isRejected($row->status) && $row->note !== null)
                                <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $row->note }}</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="font-mono text-xs whitespace-normal break-words">{{ $row->new_name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($url = $this->documentUrl($row->document))
                                <flux:button :href="$url" size="xs" variant="ghost" icon="arrow-down-tray">
                                    Descargar
                                </flux:button>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4">No hay archivos PDF/XML que procesar.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        @if ($warnings !== [])
            <div>
                <flux:heading size="sm">Advertencias</flux:heading>

                <ul class="mt-2 space-y-1">
                    @foreach ($warnings as $warning)
                        <li class="text-sm text-amber-600 dark:text-amber-500">⚠ {{ trim($warning) }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="space-y-1">
            <flux:text>{{ $okFiles }}/{{ $totalFiles }} procesados correctamente.</flux:text>

            @if ($ignoredFiles > 0)
                <flux:text variant="subtle">
                    {{ $ignoredFiles }} archivo(s) ignorado(s) por extensión no soportada.
                </flux:text>
            @endif
        </div>

        <div class="flex gap-2">
            @if ($this->downloadUrl !== null)
                <flux:button :href="$this->downloadUrl" variant="primary" icon="arrow-down-tray">
                    Descargar ZIP
                </flux:button>
            @endif

            <flux:button variant="ghost" wire:click="startOver">Procesar otros archivos</flux:button>
        </div>
    @endif
</div>
