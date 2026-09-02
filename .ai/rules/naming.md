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
