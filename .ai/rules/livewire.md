---
paths:
  - 'resources/views/livewire/**'
---

# Livewire

## flux:label se asocia al primer input del flux:field
Un `<flux:label>` dentro de `<flux:field>` se asocia al primer input del bloque. Si el campo es un contenedor con varios inputs propios, no uses `flux:field`: arma el label con `flux:heading` + `flux:text` y ponle `role="group"` y `aria-label` al contenedor.
