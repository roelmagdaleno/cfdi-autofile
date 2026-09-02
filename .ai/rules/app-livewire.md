---
paths:
  - 'app/Livewire/**'
---

# App Livewire

## Read #[Computed] properties as properties, and declare them with @property-read
Reach a `#[Computed]` method as `$this->foo`, never `$this->foo()`. Property access goes through Livewire's `__get`, which is what memoizes the value for the request; calling the method re-runs the body (and the query) every time. Livewire also ships `CannotCallComputedDirectlyException` for this — it is unwired in the current version, so direct calls silently work today and could start throwing on upgrade.

PHPStan cannot see magic properties, so declare them in a class-level docblock: `@property-read string $template`. That is the fix for "Access to an undefined property" on a computed — do not switch to a method call to silence it. The annotation stays load-bearing: typos and wrong scalar types are still caught.

Only computed methods take no arguments. A plain public method with parameters (e.g. `BrowseDocuments::downloadUrl(Document $d)`) is not computed and is called normally.
