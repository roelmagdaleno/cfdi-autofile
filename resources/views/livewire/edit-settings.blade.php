<div class="mx-auto w-full max-w-2xl space-y-6 p-6">
    <div>
        <flux:heading size="xl">Ajustes</flux:heading>
        <flux:subheading>Tu RFC y la plantilla con la que se renombran los CFDIs.</flux:subheading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:input
            wire:model="rfc"
            label="Tu RFC"
            description="Se usa para saber si eres el emisor (ingreso) o el receptor (gasto). Se guarda en mayúsculas."
            placeholder="BBBB010101BBB"
        />

        {{--
            El boton de cada pieza inserta en la seleccion que tenga el input, o
            al final si no esta enfocado. La posicion se lee del DOM en el clic
            y no de un valor horneado en el `x-data`: el remorfeo de Livewire no
            reevalua `x-data` y ese valor se quedaria congelado.

            El `mousedown.prevent` es lo que hace que la insercion en el cursor
            funcione: sin el, el boton se lleva el foco antes del `click` y para
            entonces el input ya perdio la seleccion.
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
                label="Plantilla del nombre"
                class="font-mono"
                :placeholder="\App\Support\AppSettings::DEFAULT_TEMPLATE"
            />

            <div class="space-y-2">
                <div class="flex items-center justify-between gap-4">
                    <flux:text variant="subtle" class="text-xs">
                        Agregar una pieza donde tengas el cursor
                    </flux:text>

                    <flux:button
                        type="button"
                        size="xs"
                        variant="subtle"
                        wire:click="resetTemplate"
                    >
                        Restaurar la predeterminada
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
                                {{ $placeholder->value }}
                            </flux:button>
                        </flux:tooltip>
                    @endforeach
                </div>

                <flux:text variant="subtle" class="text-xs">
                    Un <code>&#123;placeholder&#125;</code> que escribas a mano y no esté en la lista se
                    deja tal cual en el nombre.
                </flux:text>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-white/10 dark:bg-white/5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <flux:heading size="sm">Ejemplo</flux:heading>

                <flux:radio.group wire:model.live="previewType" variant="segmented" size="sm">
                    <flux:radio value="G" label="Gasto (G)" />
                    <flux:radio value="I" label="Ingreso (I)" />
                </flux:radio.group>
            </div>

            <flux:text class="mt-3 font-mono break-all">{{ $this->preview }}</flux:text>
        </div>

        <flux:button type="submit" variant="primary">Guardar</flux:button>
    </form>
</div>
