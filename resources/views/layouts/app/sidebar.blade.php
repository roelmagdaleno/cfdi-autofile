<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" :href="route('home')" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group class="grid">
                    <flux:sidebar.item icon="arrow-up-tray" :href="route('home')" :current="request()->routeIs('home')" wire:navigate>
                        {{ __('ui.nav.process') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="document-text" :href="route('documents.index')" :current="request()->routeIs('documents.*')" wire:navigate>
                        {{ __('ui.nav.documents') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="building-office-2" :href="route('counterparties.index')" :current="request()->routeIs('counterparties.*')" wire:navigate>
                        {{ __('ui.nav.counterparties') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="cog-6-tooth" :href="route('settings.edit')" :current="request()->routeIs('settings.*')" wire:navigate>
                        {{ __('ui.nav.settings') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:radio.group x-data variant="segmented" size="sm" x-model="$flux.appearance" class="mx-3 mb-3">
                <flux:radio value="light" icon="sun" />
                <flux:radio value="dark" icon="moon" />
                <flux:radio value="system" icon="computer-desktop" />
            </flux:radio.group>
        </flux:sidebar>

        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:heading>cfdi-autofile</flux:heading>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
