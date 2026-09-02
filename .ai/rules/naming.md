---
paths:
  - 'app/Cfdi/Naming/**'
---

# Naming

## The placeholder catalog lives in the Placeholder enum
Adding or removing a placeholder from the file-name template is done ONLY in `App\Cfdi\Naming\Placeholder`: that is where the keys substituted by `FileNameBuilder`, the template validation pattern (`Placeholder::pattern()`) and the buttons on the settings screen all come from. Do not rewrite the list by hand in any of those three places.

The backing values are the tokens the user types (`{date}`, `{amount}`, …) and they are stored inside `settings.value`, so renaming a case is a breaking change that needs a data migration — see `database/migrations/*_translate_spanish_values_to_english.php` for the pattern.

## The credit-note suffix is translated
`FileNameBuilder::creditNoteSuffix()` reads `cfdi.credit_note_suffix`, so the active locale changes what gets written to disk. Under `es` it is ` (nota de credito)`, which is what the app produced before the codebase moved to English. The same applies to `cfdi.default_output_directory` and `cfdi.generic_counterparty`.

## TypeLetter is the identity; TypeLetters is the label on disk
`TypeLetter`'s backing values (`I`/`G`/`?`) are the canonical identity: that is what `documents.type_letter` stores, what `BrowseDocuments` filters on and what the Livewire radio/select values carry. Do not change them and do not persist a user-facing letter there.

What gets written into a file name is `App\Cfdi\Naming\TypeLetters` (configurable, 1–8 chars, stored in `settings.letter_income` / `settings.letter_expense`). `FileNameBuilder::build()` takes it as an optional last argument and `ProcessOptions::$letters` carries it through the processor; a `null` falls back to `TypeLetters::defaults()`.

`Unknown` is deliberately not configurable — it is the "your RFC is not on this invoice" case and `NameSanitizer` turns its `?` into `-`.

`TypeLetters::isValid()` rejects the characters the sanitizer would replace, so the settings screen and `cfdi:autofile` reject the same input. Reuse it instead of writing a second regex.
