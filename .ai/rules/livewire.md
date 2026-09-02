---
paths:
  - 'resources/views/livewire/**'
---

# Livewire

## flux:label binds to the first input of the flux:field
A `<flux:label>` inside a `<flux:field>` binds to the first input of the block. If the field is a container with several inputs of its own, do not use `flux:field`: build the label with `flux:heading` + `flux:text` and give the container `role="group"` and an `aria-label`.

## Page titles come from render(), not #[Title]
Attribute arguments must be constant, so a translated title cannot go in `#[Title]`. Each full-page component defines `render()` and returns `view('livewire.…')->title(__('ui.<screen>.title'))`.
