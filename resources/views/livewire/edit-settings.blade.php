<div class="mx-auto w-full max-w-2xl space-y-6 p-6">
    <div>
        <flux:heading size="xl">{{ __('ui.settings.title') }}</flux:heading>
        <flux:subheading>{{ __('ui.settings.subheading') }}</flux:subheading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:input
            wire:model="rfc"
            :label="__('ui.settings.rfc_label')"
            :description="__('ui.settings.rfc_description')"
            placeholder="BBBB010101BBB"
        />

        {{--
            Each piece's button inserts into whatever selection the input holds,
            or at the end when it is not focused. The position is read from the
            DOM on click and not from a value baked into `x-data`: Livewire's
            re-morph does not re-evaluate `x-data` and that value would stay
            frozen.

            The `mousedown.prevent` is what makes insertion at the cursor work:
            without it the button takes focus before the `click`, and by then the
            input has already lost its selection.
        --}}
        <div
            class="space-y-4"
            x-data="{
                async insert(name) {
                    const input = $refs.template;
                    const token = '{' + name + '}';
                    const focused = document.activeElement === input;
                    const start = focused ? input.selectionStart : input.value.length;
                    const end = focused ? input.selectionEnd : start;

                    await $wire.insertPlaceholder(name, start, end);

                    input.focus();
                    input.setSelectionRange(start + token.length, start + token.length);
                },
            }"
        >
            <flux:input
                x-ref="template"
                wire:model.live.debounce.400ms="template"
                :label="__('ui.settings.template_label')"
                class="font-mono"
                :placeholder="\App\Support\AppSettings::DEFAULT_TEMPLATE"
            />

            <div class="space-y-2">
                <div class="flex items-center justify-between gap-4">
                    <flux:text variant="subtle" class="text-xs">
                        {{ __('ui.settings.add_piece') }}
                    </flux:text>

                    <flux:button
                        type="button"
                        size="xs"
                        variant="subtle"
                        wire:click="resetTemplate"
                    >
                        {{ __('ui.settings.restore_default') }}
                    </flux:button>
                </div>

                <div class="flex flex-wrap gap-1.5">
                    @foreach ($this->placeholders as $placeholder)
                        <flux:tooltip :content="$placeholder->description()">
                            <flux:button
                                type="button"
                                size="xs"
                                variant="filled"
                                icon="plus"
                                x-on:mousedown.prevent
                                x-on:click="insert('{{ $placeholder->value }}')"
                                class="font-mono"
                            >
                                {{ $placeholder->label() }}
                            </flux:button>
                        </flux:tooltip>
                    @endforeach
                </div>

                <flux:text variant="subtle" class="text-xs">
                    {{-- The only interpolation is our own markup, never user input. --}}
                    {!! __('ui.settings.unknown_placeholder', ['token' => '<code>&#123;placeholder&#125;</code>']) !!}
                </flux:text>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-white/10 dark:bg-white/5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <flux:heading size="sm">{{ __('ui.settings.example') }}</flux:heading>

                <flux:radio.group wire:model.live="previewType" variant="segmented" size="sm">
                    <flux:radio value="G" :label="__('ui.settings.expense')" />
                    <flux:radio value="I" :label="__('ui.settings.income')" />
                </flux:radio.group>
            </div>

            <flux:text class="mt-3 font-mono break-all">{{ $this->preview }}</flux:text>
        </div>

        <flux:button type="submit" variant="primary">{{ __('ui.common.save') }}</flux:button>
    </form>
</div>
