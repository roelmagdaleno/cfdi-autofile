---
paths:
  - 'resources/views/**'
---

# Views

## Trampas de Flux al armar subidas de archivos
`<flux:input type="file">` es un wrapper `wire:ignore` con su propio `x-data="fluxInputFile(...)"`, un input `sr-only` y un overlay que intercepta el clic: no se le puede inyectar un `DataTransfer` ni sincronizar su lista de nombres cuando el servidor limpia la propiedad. Para drag & drop o subidas acumuladas usa un `<input type="file" class="sr-only" x-ref>` plano con `wire:model` y dibuja tu propia lista (ver la zona de drop en `livewire/run-cfdis.blade.php`).

Un `<flux:button>` con el atributo `disabled` esconde su contenido (`[&[disabled]>:not([data-flux-loading-indicator])]:opacity-0`) y muestra el spinner: deshabilitarlo por validación se ve como "cargando". Deshabilítalo sólo cuando de verdad está ocupado.

`x-data` NO se reevalúa cuando Livewire remorfea el DOM, así que un valor horneado con `@js($prop)` se queda congelado; lee del componente con `$wire.prop`.
