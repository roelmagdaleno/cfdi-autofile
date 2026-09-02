<div
    class="mx-auto w-full max-w-6xl space-y-6 p-6"
    x-data="{
        dragging: 0,
        uploading: false,
        progress: 0,
        maxFiles: @js(\App\Livewire\RunCfdis::MAX_FILES),
        extensions: ['pdf', 'xml'],

        get accepting() {
            // Read from the component and not from the render: x-data is not
            // re-evaluated when Livewire re-morphs, so a baked value would stay frozen.
            return $wire.step === @js(\App\Livewire\RunCfdis::STEP_UPLOAD)
                && @js($this->configuredRfc !== null)
                && ! this.uploading;
        },

        /** Dragging text or a link must not light up the overlay. */
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

            // `items` stops being valid once the handler returns, so the entries
            // are taken synchronously before any await.
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

        /** Walks folders so dropping the SAT download as-is just works. */
        async walk(entry, files) {
            if (entry.isFile) {
                if (this.supported(entry.name)) {
                    files.push(await new Promise((resolve, reject) => entry.file(resolve, reject)));
                }

                return;
            }

            const reader = entry.createReader();

            // `readEntries` delivers in batches: it has to be called until it returns empty.
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

            // The slice keeps PHP's `max_file_uploads` from truncating silently.
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
        <flux:heading size="xl">{{ __('ui.run.title') }}</flux:heading>
        <flux:subheading>{{ __('ui.run.subheading') }}</flux:subheading>
    </div>

    @if ($step === 'upload')
        @if ($this->configuredRfc === null)
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.heading>{{ __('ui.run.missing_rfc_heading') }}</flux:callout.heading>
                <flux:callout.text>
                    {{ __('ui.run.missing_rfc_text') }}
                </flux:callout.text>
                <x-slot:actions>
                    <flux:button :href="route('settings.edit')" wire:navigate size="sm">
                        {{ __('ui.run.go_to_settings') }}
                    </flux:button>
                </x-slot:actions>
            </flux:callout>
        @else
            <flux:card variant="soft" size="sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <flux:icon.identification class="size-5 shrink-0 text-zinc-400" />

                        <div>
                            <flux:text variant="subtle" class="text-xs">{{ __('ui.run.your_rfc') }}</flux:text>
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
                        {{ __('ui.run.change') }}
                    </flux:button>
                </div>

                <flux:separator variant="subtle" class="my-3" />

                <flux:text variant="subtle" class="text-xs">{{ __('ui.run.naming_preview') }}</flux:text>

                {{--
                    Every known piece comes out as a pill with its explanation in
                    the tooltip; the text in between keeps its spaces via
                    `whitespace-pre` so the template reads exactly as written.
                --}}
                <div class="mt-2.5 flex flex-wrap items-center gap-x-0.2 gap-y-1.5 font-mono text-sm">
                    @foreach ($this->templateSegments as $segment)
                        @if ($segment['placeholder'] !== null)
                            <flux:tooltip :content="$segment['placeholder']->description($this->typeLetters)">
                                <span
                                    tabindex="0"
                                    class="rounded-md bg-zinc-200 px-1.5 py-0.5 text-xs text-zinc-800 ring-1 ring-zinc-950/[0.08] dark:bg-white/15 dark:text-zinc-100 dark:ring-white/10"
                                >
                                    {{ $segment['placeholder']->label() }}
                                </span>
                            </flux:tooltip>
                        @else
                            <span class="whitespace-pre text-zinc-500 dark:text-zinc-400">{{ $segment['text'] }}</span>
                        @endif
                    @endforeach
                </div>

                <flux:text variant="subtle" class="mt-3.5 text-xs break-all">
                    {{ __('ui.run.example') }} <span class="font-mono">{{ $this->templateExample }}</span>
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

                <flux:heading class="mt-3">{{ __('ui.run.drop_heading') }}</flux:heading>

                <flux:text class="mt-1">
                    {{ __('ui.run.drop_text') }}
                </flux:text>

                <flux:button
                    type="button"
                    variant="filled"
                    size="sm"
                    class="mt-4"
                    x-on:click="$refs.picker.click()"
                    :disabled="$this->configuredRfc === null"
                >
                    {{ __('ui.run.pick_files') }}
                </flux:button>

                <flux:text variant="subtle" class="mt-3">
                    {{ __('ui.run.limits', ['max' => \App\Livewire\RunCfdis::MAX_FILES]) }}
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

                <flux:text variant="subtle">{{ __('ui.run.uploading') }} <span x-text="progress"></span>%</flux:text>
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
                <span wire:loading.remove wire:target="analyze">{{ __('ui.run.process') }}</span>
                <span wire:loading wire:target="analyze">{{ __('ui.run.processing') }}</span>
            </flux:button>
        </form>

        <div
            x-show="dragging > 0"
            style="display: none"
            class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-900/50 p-6 backdrop-blur-sm"
        >
            <div class="pointer-events-none rounded-2xl border-2 border-dashed border-white/60 px-12 py-10 text-center">
                <flux:icon.arrow-up-tray class="mx-auto size-10 text-white" />

                <p class="mt-4 text-lg font-medium text-white">{{ __('ui.run.overlay_heading') }}</p>

                <p class="mt-1 text-sm text-white/70">{{ __('ui.run.overlay_text') }}</p>
            </div>
        </div>
    @endif

    @if ($step === 'counterparties')
        <div>
            <flux:heading size="lg">{{ __('ui.run.new_counterparties') }}</flux:heading>
            <flux:subheading>
                {{ __('ui.run.new_counterparties_text') }}
            </flux:subheading>
        </div>

        <form wire:submit="confirmCounterparties" class="space-y-4">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('ui.common.rfc') }}</flux:table.column>
                    <flux:table.column>{{ __('ui.common.legal_name') }}</flux:table.column>
                    <flux:table.column>{{ __('ui.run.name_to_use') }}</flux:table.column>
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
                    {{ __('ui.run.save_and_process') }}
                </flux:button>

                <flux:button variant="ghost" wire:click="startOver" type="button">{{ __('ui.common.cancel') }}</flux:button>
            </div>
        </form>
    @endif

    @if ($step === 'results')
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('ui.common.status') }}</flux:table.column>
                <flux:table.column>{{ __('ui.common.original') }}</flux:table.column>
                <flux:table.column>{{ __('ui.common.new_name') }}</flux:table.column>
                @if ($this->showsCloudColumn)
                    <flux:table.column>{{ __('ui.cloud.column') }}</flux:table.column>
                @endif
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
                                {{ $this->statusLabel($row->status) }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="max-w-xs whitespace-normal break-words">
                            <span class="font-mono text-xs">{{ $row->original_name }}</span>

                            {{-- The reason only exists on rejected rows: on the rest the note
                                 would read "copied" on every row and add nothing. --}}
                            @if ($this->isRejected($row->status) && $row->note !== null)
                                <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $row->note }}</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="font-mono text-xs whitespace-normal break-words">{{ $row->new_name ?? '—' }}</flux:table.cell>
                        @if ($this->showsCloudColumn)
                            <flux:table.cell class="max-w-xs whitespace-normal break-words">
                                @if ($row->drive_link !== null)
                                    <flux:link :href="$row->drive_link" target="_blank" class="text-xs">
                                        {{ __('ui.cloud.open', ['provider' => __('ui.cloud.provider.google_drive')]) }}
                                    </flux:link>
                                @elseif ($status = $row->driveStatus())
                                    <flux:text variant="subtle" class="text-xs">{{ $status->label() }}</flux:text>

                                    @if ($row->drive_note !== null)
                                        <span class="mt-1 block text-xs text-zinc-500 dark:text-zinc-400">{{ $row->drive_note }}</span>
                                    @endif
                                @else
                                    <span class="text-xs text-zinc-400">—</span>
                                @endif
                            </flux:table.cell>
                        @endif
                        <flux:table.cell>
                            @if ($url = $this->documentUrl($row->document))
                                <flux:button :href="$url" size="xs" variant="ghost" icon="arrow-down-tray">
                                    {{ __('ui.common.download') }}
                                </flux:button>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell :colspan="$this->showsCloudColumn ? 5 : 4">{{ __('ui.run.no_files') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        @if ($warnings !== [])
            <div>
                <flux:heading size="sm">{{ __('ui.run.warnings') }}</flux:heading>

                <ul class="mt-2 space-y-1">
                    @foreach ($warnings as $warning)
                        <li class="text-sm text-amber-600 dark:text-amber-500">⚠ {{ trim($warning) }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="space-y-1">
            <flux:text>{{ __('ui.run.summary', ['ok' => $okFiles, 'total' => $totalFiles]) }}</flux:text>

            @if ($ignoredFiles > 0)
                <flux:text variant="subtle">
                    {{ __('ui.run.ignored', ['count' => $ignoredFiles]) }}
                </flux:text>
            @endif
        </div>

        <div class="flex gap-2">
            @if ($this->downloadUrl !== null)
                <flux:button :href="$this->downloadUrl" variant="primary" icon="arrow-down-tray">
                    {{ __('ui.run.download_zip') }}
                </flux:button>
            @endif

            @if ($this->hasFailedCloudUploads)
                <flux:button
                    variant="filled"
                    icon="arrow-path"
                    wire:click="retryCloudUploads"
                    wire:loading.attr="disabled"
                    wire:target="retryCloudUploads"
                >
                    {{ __('ui.cloud.retry') }}
                </flux:button>
            @endif

            <flux:button variant="ghost" wire:click="startOver">{{ __('ui.run.start_over') }}</flux:button>
        </div>
    @endif
</div>
