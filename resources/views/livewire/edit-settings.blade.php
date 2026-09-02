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
            The template card is a container with several inputs, so it does NOT
            use `flux:field`: a `flux:label` inside one binds to the first input
            of the block. It is a labelled group instead, like the cloud card.
        --}}
        <div
            role="group"
            aria-label="{{ __('ui.settings.template_heading') }}"
            class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-white/10"
        >
            <div>
                <flux:heading size="sm">{{ __('ui.settings.template_heading') }}</flux:heading>
                <flux:text variant="subtle" class="mt-1 text-xs">{{ __('ui.settings.template_subheading') }}</flux:text>
            </div>

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
                            <flux:tooltip :content="$placeholder->description($this->typeLetters)">
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

            {{--
                The labels only change what `{type}` writes on disk: the archive
                keeps storing I/G, so the filters in Documents keep working after a
                change here.
            --}}
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input
                    wire:model.live.debounce.400ms="letterIncome"
                    :label="__('ui.settings.letter_income_label')"
                    class="font-mono"
                    maxlength="{{ \App\Cfdi\Naming\TypeLetters::MAX_LENGTH }}"
                />

                <flux:input
                    wire:model.live.debounce.400ms="letterExpense"
                    :label="__('ui.settings.letter_expense_label')"
                    class="font-mono"
                    maxlength="{{ \App\Cfdi\Naming\TypeLetters::MAX_LENGTH }}"
                />

                <flux:text variant="subtle" class="text-xs sm:col-span-2">
                    {{ __('ui.settings.letters_description', [
                        'token' => \App\Cfdi\Naming\Placeholder::Type->token(),
                        'max' => \App\Cfdi\Naming\TypeLetters::MAX_LENGTH,
                    ]) }}
                </flux:text>
            </div>

            <div class="rounded-lg bg-zinc-50 p-3 dark:bg-white/5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <flux:text variant="subtle" class="text-xs">{{ __('ui.settings.example') }}</flux:text>

                    <flux:radio.group wire:model.live="previewType" variant="segmented" size="sm">
                        <flux:radio
                            :value="\App\Cfdi\Data\TypeLetter::Expense->value"
                            :label="__('ui.settings.expense', ['letter' => $this->typeLetters->expense])"
                        />
                        <flux:radio
                            :value="\App\Cfdi\Data\TypeLetter::Income->value"
                            :label="__('ui.settings.income', ['letter' => $this->typeLetters->income])"
                        />
                    </flux:radio.group>
                </div>

                <flux:text class="mt-3 font-mono text-sm break-all">{{ $this->preview }}</flux:text>
            </div>
        </div>

        {{--
            The cloud card is a container with several inputs, so it does NOT use
            `flux:field`: a `flux:label` inside one binds to the first input of
            the block. It is a labelled group instead.

            The provider's own fields sit behind a check on which provider is
            selected, so a second provider is a branch added next to this one
            rather than an edit to it.
        --}}
        @php($providerLabel = __('ui.cloud.provider.'.$cloudProvider))
        <div
            role="group"
            aria-label="{{ __('ui.cloud.heading') }}"
            class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-white/10"
        >
            <div>
                <flux:heading size="sm">{{ __('ui.cloud.heading') }}</flux:heading>
                <flux:text variant="subtle" class="mt-1 text-xs">
                    {{ __('ui.cloud.subheading', ['provider' => $providerLabel]) }}
                </flux:text>
            </div>

            @if (session('cloud_status'))
                <flux:callout variant="success" icon="check-circle" :heading="session('cloud_status')" />
            @endif

            @if (session('cloud_error'))
                <flux:callout variant="danger" icon="exclamation-triangle" :heading="session('cloud_error')" />
            @endif

            <flux:select wire:model.live="cloudProvider" :label="__('ui.cloud.provider_label')">
                @foreach (\App\Cloud\Data\CloudProvider::cases() as $provider)
                    <flux:select.option :value="$provider->value">{{ $provider->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:switch
                wire:model.live="cloudEnabled"
                :label="__('ui.cloud.enable', ['provider' => $providerLabel])"
                :description="__('ui.cloud.enable_description')"
            />

            <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-zinc-50 p-3 dark:bg-white/5">
                <flux:text class="text-sm">
                    @if ($cloudConnected && $cloudAccount !== '')
                        {{ __('ui.cloud.connected_as', ['account' => $cloudAccount]) }}
                    @elseif ($cloudConnected)
                        {{-- Connected, but the provider would not say which account. --}}
                        {{ __('ui.cloud.connected_unknown', ['provider' => $providerLabel]) }}
                    @else
                        {{ __('ui.cloud.not_connected', ['provider' => $providerLabel]) }}
                    @endif
                </flux:text>

                <div class="flex gap-2">
                    <flux:button
                        size="xs"
                        variant="filled"
                        icon="arrow-top-right-on-square"
                        :href="route('cloud.'.$cloudProvider.'.connect')"
                    >
                        {{ $cloudConnected ? __('ui.cloud.reconnect') : __('ui.cloud.connect', ['provider' => $providerLabel]) }}
                    </flux:button>

                    @if ($cloudConnected)
                        <flux:button type="button" size="xs" variant="subtle" wire:click="disconnectCloud">
                            {{ __('ui.cloud.disconnect') }}
                        </flux:button>
                    @endif
                </div>
            </div>

            @if ($cloudEnabled && $cloudProvider === \App\Cloud\Data\CloudProvider::GoogleDrive->value)
                <flux:input
                    wire:model="googleRootFolderId"
                    :label="__('ui.cloud.google_drive.root_folder_label')"
                    :description="__('ui.cloud.google_drive.root_folder_description')"
                    class="font-mono"
                    placeholder="1a2B3cD4eF5gH6iJ7kL8mN9oP"
                />

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input
                        wire:model.live.debounce.400ms="googleIncomeFolder"
                        :label="__('ui.cloud.income_folder_label')"
                        class="font-mono"
                    />

                    <flux:input
                        wire:model.live.debounce.400ms="googleExpenseFolder"
                        :label="__('ui.cloud.expense_folder_label')"
                        class="font-mono"
                    />

                    <flux:text variant="subtle" class="text-xs sm:col-span-2">
                        {{ __('ui.cloud.folders_description', ['token' => \App\Cloud\Google\GoogleFolderTemplate::TOKEN]) }}
                    </flux:text>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-zinc-50 p-3 dark:bg-white/5">
                    <div>
                        <flux:text variant="subtle" class="text-xs">{{ __('ui.cloud.preview') }}</flux:text>
                        <flux:text class="mt-1 font-mono text-sm break-all">
                            {{ $this->googleFolderPreview['income'] }} · {{ $this->googleFolderPreview['expense'] }}
                        </flux:text>
                    </div>

                    <flux:button
                        type="button"
                        size="xs"
                        variant="filled"
                        icon="magnifying-glass"
                        wire:click="verifyCloudDestinations"
                        wire:loading.attr="disabled"
                        wire:target="verifyCloudDestinations"
                    >
                        {{ __('ui.cloud.verify') }}
                    </flux:button>
                </div>

                <flux:text variant="subtle" class="text-xs">{{ __('ui.cloud.google_drive.scope_notice') }}</flux:text>
            @endif
        </div>

        <flux:button type="submit" variant="primary">{{ __('ui.common.save') }}</flux:button>
    </form>
</div>
