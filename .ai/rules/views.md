---
paths:
  - 'resources/views/**'
---

# Views

## Flux traps when building file uploads
`<flux:input type="file">` is a `wire:ignore` wrapper with its own `x-data="fluxInputFile(...)"`, an `sr-only` input and an overlay that intercepts the click: you cannot inject a `DataTransfer` into it, nor sync its list of names when the server clears the property. For drag & drop or cumulative uploads use a plain `<input type="file" class="sr-only" x-ref>` with `wire:model` and draw your own list (see the drop zone in `livewire/run-cfdis.blade.php`).

A `<flux:button>` carrying the `disabled` attribute hides its content (`[&[disabled]>:not([data-flux-loading-indicator])]:opacity-0`) and shows the spinner: disabling it for validation looks like "loading". Only disable it when it is genuinely busy.

`x-data` is NOT re-evaluated when Livewire re-morphs the DOM, so a value baked in with `@js($prop)` stays frozen; read from the component with `$wire.prop`.

## All copy goes through `__()`
There are no hardcoded strings in these views. Keys live in `lang/{locale}/ui.php` and are English and descriptive (`ui.run.drop_heading`), never the English sentence itself — that keeps `lang/es` from silently breaking when English copy is reworded.
