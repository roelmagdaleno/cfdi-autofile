# cfdi-autofile — Specification for the migration to Laravel

Reference document for reimplementing the Python `cfdi-autofile` CLI as a Laravel
application. It was written so that Claude Code could execute it inside a freshly
created Laravel project on Herd, without having to read the Python code.

**Source of truth for behavior:** the Python project at
`/Users/roelmagdaleno/Code/Roel/cfdi-autofile` (`src/cfdi_autofile/` and `tests/`).
Where this document and the Python code disagree, the Python code wins.

> **Status.** The migration is done. This document is kept as the record of what
> was specified, translated to English and with the identifiers realigned to the
> names that were actually built. Several decisions below were superseded during
> implementation — they are called out inline with a **Built as** note. For
> anything about the code as it stands today, the code itself and `.ai/rules/`
> are authoritative, not this file.

---

## 1. What the product does

It renames CFDIs (Mexican tax invoices: PDF + XML) according to a configurable
template, and **rejects** files that are not real CFDIs.

The scope is deliberately small: **only** renaming + validation. There is no
folder watcher, no Google Drive/Sheets, no SAT integration, and nothing running
in the background.

Non-negotiable product rules:

1. **A value is never invented.** Anything that could not be extracted with
   certainty stays `null`, and the file is either flagged for review or rejected.
2. **Rejected files are never copied or moved.** They stay where they were.
3. **The XML is the source of truth.** The PDF is only a low-confidence fallback
   when there is no XML.
4. **A PDF + XML sharing a base name is a single invoice.** Both end up with the
   same new name.

---

## 2. Architecture decisions already taken

| Decision | Value |
| --- | --- |
| Framework | Laravel 12, PHP 8.4 (Herd) — **Built as:** Laravel 13 |
| Interfaces | **Two**: web app + Artisan command, both on top of one shared service core |
| Persistence | **SQLite** (`database/database.sqlite`) for settings and counterparties |
| Users | **A single user, no login.** It runs locally on Herd. No `users` table, no Breeze, no Fortify |
| Parity | **Exact** with the Python, behavior and output *format*. **Built as:** the wording is translated and lives in `lang/`; `lang/es` reproduces the original Spanish strings verbatim, so parity is preserved under the `es` locale. The 4 test files are ported to Pest |
| Frontend | Blade + Tailwind + Alpine.js (or Livewire if preferred). No SPA, no complex build — **Built as:** Livewire 4 + Flux + Tailwind v4 |

### Composer dependencies

| Package | What for |
| --- | --- |
| `smalot/pdfparser` | Extracting text from PDFs. The equivalent of `pdfplumber` — **Built as:** `prinsfrank/pdfparser` |
| `ext-dom` / `ext-libxml` | XML parsing (ships with PHP) |
| `ext-zip` | Packaging the web download |
| `ext-intl` | Unicode normalization for stripping accents |
| `pestphp/pest` (dev) | Tests |

> **Note on the PDF parser:** it is not identical to `pdfplumber` in how it
> orders text. Extraction must be isolated behind an interface
> (`PdfTextExtractor`) so the library can be swapped without touching the
> validation logic, and the tests must be able to inject text directly.

---

## 3. Target file structure

The layout below is what was originally specified. **Built as:** the classes were
given English names; the mapping is in the right-hand column.

```
app/
├── Cfdi/
│   ├── Data/
│   │   ├── CfdiData.php            # readonly DTO with the extracted data
│   │   └── FileResult.php          # DTO for one row of the summary
│   ├── Exceptions/
│   │   └── CfdiRejectedException.php
│   ├── Parsing/
│   │   ├── XmlCfdiParser.php       # source of truth, high confidence
│   │   ├── PdfCfdiParser.php       # fallback, low confidence
│   │   ├── PdfTextExtractor.php    # interface
│   │   └── PrinsFrankPdfTextExtractor.php
│   ├── Naming/
│   │   ├── NameSanitizer.php       # stripAccents / sanitize
│   │   ├── CounterpartyNameSuggester.php   # legal name -> short name
│   │   ├── CounterpartyResolver.php
│   │   └── FileNameBuilder.php     # applies the template
│   ├── CfdiProcessor.php           # orchestrator: groups, validates, renames
│   └── FileGrouper.php             # PDF+XML by stem
├── Console/Commands/
│   └── CfdiAutofileCommand.php     # php artisan cfdi:autofile
├── Livewire/                       # Built as: Livewire components, not controllers
│   ├── RunCfdis.php
│   ├── BrowseDocuments.php
│   ├── ManageCounterparties.php
│   └── EditSettings.php
├── Models/
│   ├── Setting.php
│   └── Counterparty.php
└── Support/
    └── AppSettings.php             # typed facade over the settings table
```

---

## 4. Data model (SQLite)

### `settings` (key-value, one row per key)

```php
Schema::create('settings', function (Blueprint $table) {
    $table->id();
    $table->string('key')->unique();
    $table->text('value')->nullable();
    $table->timestamps();
});
```

Keys in use:

| Key | Contents | Default |
| --- | --- | --- |
| `rfc` | Your own RFC, always stored UPPERCASED and trimmed | `null` |
| `template` | File-name template | `{date} {type} {counterparty} - {amount}{credit_note}` |

The `App\Support\AppSettings` helper exposes `rfc()`, `setRfc(string)`,
`template()`, `setTemplate(string)`. `setRfc` applies `trim` + `strtoupper`.

### `counterparties` (cache of RFC -> short name)

```php
Schema::create('counterparties', function (Blueprint $table) {
    $table->id();
    $table->string('rfc')->unique();
    $table->string('name');
    $table->string('legal_name')->nullable(); // informative, the one from the CFDI
    $table->timestamps();
});
```

`rfc` is always UPPERCASED. `name` always goes through `NameSanitizer::sanitize()`
before being stored.

> No history table and no table of processed invoices is created. That is out of
> scope. **Built as:** superseded — `runs`, `run_files` and `documents` were added
> so the web could survive a browser refresh and keep an archive of processed
> CFDIs. See `.ai/rules/documents.md`.

---

## 5. The `CfdiData` DTO

```php
final class CfdiData
{
    public function __construct(
        public ?string $date = null,              // ISO exactly as it comes: "2026-02-14T11:32:05"
        public ?string $total = null,             // raw string: "11600.00"
        public ?string $invoiceType = null,       // "I" | "E" | "N" | ... | null
        public ?string $issuerRfc = null,
        public ?string $issuerName = null,
        public ?string $recipientRfc = null,
        public ?string $recipientName = null,
        public ?string $uuid = null,
        public ?string $replacedUuid = null,
        public Confidence $confidence = Confidence::High,  // High (xml) | Low (pdf)
        public ?SourceFormat $source = null,               // Xml | Pdf
        public array $warnings = [],
    ) {}

    public function isCreditNote(): bool
    {
        return $this->invoiceType === 'E';
    }
}
```

---

## 6. XML parsing and validation (`XmlCfdiParser`)

Confidence `High`, source `Xml`.

The node and attribute names below stay in Spanish on purpose: they are the SAT's
CFDI schema, not app vocabulary.

### Namespaces

```php
const NS_CFDI_3 = 'http://www.sat.gob.mx/cfd/3';
const NS_CFDI_4 = 'http://www.sat.gob.mx/cfd/4';
const NS_TFD    = 'http://www.sat.gob.mx/TimbreFiscalDigital';
```

### Algorithm

1. Load the XML. If it is malformed, throw
   `CfdiRejectedException` with `cfdi.rejected.malformed_xml`.
   Use `libxml_use_internal_errors(true)` and do not let PHP emit warnings.
2. **The root node must be named `Comprobante`** (local name, ignoring the
   prefix) **and live in the namespace** `NS_CFDI_3` or `NS_CFDI_4`. Otherwise:
   `cfdi.rejected.not_a_cfdi`.
3. Read the `Fecha`, `Total` and `TipoDeComprobante` attributes off the root node.
4. Find the **direct child** with local name `Emisor` → `Rfc`, `Nombre` attributes.
5. Find the **direct child** with local name `Receptor` → `Rfc`, `Nombre` attributes.
6. Inside the direct child `Complemento`, walk **all descendants** looking for the
   first one whose local name is `TimbreFiscalDigital` **and** whose namespace is
   `NS_TFD`. That yields `uuid` (the `UUID` attribute, **verbatim, without
   changing its case**).
7. If the direct child `CfdiRelacionados` exists **with** `TipoRelacion="04"`,
   take the `UUID` of its `CfdiRelacionado` child → `replacedUuid`, and **add a
   warning** (it is not a rejection): `cfdi.warning.replaces`.

### Rejections, in this exact order

| Condition | Message key |
| --- | --- |
| `Fecha` missing | `cfdi.rejected.missing_date` |
| `Total` missing | `cfdi.rejected.missing_total` |
| Emisor RFC missing | `cfdi.rejected.missing_issuer_rfc` |
| Receptor RFC missing | `cfdi.rejected.missing_recipient_rfc` |

> Note: looking up by local name + namespace is intentional. The namespace prefix
> (`cfdi:`, `c:`, none) **must not** matter. With `DOMDocument`, compare
> `$node->localName` and `$node->namespaceURI`.

---

## 7. PDF parsing and validation (`PdfCfdiParser`)

Confidence `Low`, source `Pdf`. Only used **when there is no XML**, or when the
XML was rejected.

### Regular expressions (ported literally, with `/u`)

```php
// Mexican RFC: 3 letters (company) or 4 (individual) + 6 date digits + 3 of the homoclave
const RFC_RE = '/\b([A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3})\b/u';

const UUID_RE = '/\b[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\b/';

// The (?<!sub) keeps "Subtotal: $2,500.00" from being read as the total
const TOTAL_RE = '/(?<!sub)(?<![a-z])(?:gran\s+total|importe\s+total|total)\b\D{0,20}?\$?\s*'
               . '(\d{1,3}(?:,\d{3})*(?:\.\d{2})?|\d+\.\d{2})/i';

const DATE_ISO_RE = '/\b(\d{4}-\d{2}-\d{2})(?:[T ]\d{2}:\d{2}:\d{2})?\b/';
const DATE_DMY_RE = '/\b(\d{2})\/(\d{2})\/(\d{4})\b/';
```

### Validation checklist — the first one that fails is the rejection reason

1. **Is there extractable text?** If the extracted text is empty or only
   whitespace: `cfdi.rejected.pdf_no_text`.
2. **Are there at least 2 distinct, well-formed RFCs?** The search runs over the
   UPPERCASED text, preserving order of appearance and **removing duplicates**.
   If there are fewer than 2: `cfdi.rejected.pdf_not_enough_rfcs`.
3. **Is it stamped?** Either a hyphenated UUID **or** the phrase `folio fiscal`
   (case-insensitive) must appear. Otherwise: `cfdi.rejected.pdf_not_stamped`.
4. **Is there an identifiable total?** The **last** match of `TOTAL_RE` in the
   text is taken, not the first — the total usually comes at the end of the
   document. If there is none: `cfdi.rejected.pdf_no_total`.
5. **Is there a date?** `DATE_ISO_RE` is tried first; failing that, `DATE_DMY_RE`
   (`dd/mm/yyyy` → `yyyy-mm-dd`). If there is none:
   `cfdi.rejected.pdf_no_date`.

### If everything passes

- `total` = the capture, **without commas**.
- `uuid` = the UUID found, **lowercased**, or `null` if only the phrase
  "folio fiscal" was present.
- `issuerRfc` = the first RFC in order of appearance, `recipientRfc` = the second.
  **This is a heuristic and it can be the wrong way round.**
- `invoiceType` = `null`. **It is never guessed.**
- This warning is always added: `cfdi.warning.pdf_heuristic`.

---

## 8. Counterparty names

### `NameSanitizer::stripAccents(string): string`

1. Replaces `ñ` → `n` and `Ñ` → `N` **before** normalizing.
2. Normalizes to NFD and removes every diacritical mark (Unicode category `Mn`).
   With `intl`: `Normalizer::normalize($t, Normalizer::FORM_D)` and then
   `preg_replace('/\p{Mn}/u', '', $t)`.

### `NameSanitizer::sanitize(string): string`

In this order:

1. `stripAccents`
2. Removes `,` and `$`
3. Replaces `/ \ : * ? " < > |` with `-`
4. Collapses runs of whitespace into a single space
5. `trim` of spaces, then `trim` of dots at both ends

### `CounterpartyNameSuggester::suggest(?string $legalName): string`

Legal name without its legal form, at most 3 words, without accents.

1. If it comes in empty or `null`, return `''`.
2. `stripAccents`.
3. Replace with a space anything that is **not** a letter/digit/`_`/space/`&`/`.`
   (`preg_replace('/[^\w\s&.]/u', ' ', $t)`).
4. For each word: its base form is the word **without dots and UPPERCASED**. If
   the base is empty or is in `LEGAL_FORMS`, it is discarded. Otherwise the
   original word is kept, **without the dots at its ends**.
5. If no word was left, all the original words are used.
6. Return the first **3** words joined by a space, trimmed.

#### `LEGAL_FORMS` (exact set, compared UPPERCASED)

```
SA, S, A, DE, CV, C, V, SAPI, SAB, SC, AC, SRL, RL, SDE, S.A., S.A, SAS,
SOFOM, ENR, MI, SPR, SNC, SAPIB, B, BURSATIL, CAPITAL, VARIABLE, ANONIMA,
SOCIEDAD, PROMOTORA, INVERSION, RESPONSABILIDAD, LIMITADA, CIVIL, LTD,
LLC, INC, CORP,
DEL, LA, LAS, LOS, EL, Y, E
```

These stay in Spanish: they match the Mexican legal-entity names that arrive in
the CFDI's `Emisor@Nombre`. The last ones are connectors that add nothing to the
short name.

Test cases:

| Input | Output |
| --- | --- |
| `SERVICIOS CONTABLES DEL NORTE SA DE CV` | `SERVICIOS CONTABLES NORTE` |
| `Café Piñón S.A. de C.V.` | `Cafe Pinon` |
| `TELEFONOS DE MEXICO SAB DE CV` | `TELEFONOS MEXICO` |

### `CounterpartyResolver::resolve(?string $rfc, ?string $legalName, CounterpartyNamePrompt $prompt): string`

The RFC is normalized with `trim` + `strtoupper` on the way in.

1. If the RFC is **generic** (`XAXX010101000` or `XEXX010101000`), return
   `cfdi.generic_counterparty` (`General`) **without touching the database**.
2. If the RFC is already in the `counterparties` table with a non-empty `name`,
   use it as-is and **ask nothing**.
3. Compute `$suggestion = CounterpartyNameSuggester::suggest($legalName) ?: $rfc`.
4. **Interactive mode:** ask the user for the name, showing the suggestion as the
   default. If the user answers empty (or cancels), the suggestion is used.
   **Non-interactive mode:** use the suggestion without asking.
   **Built as:** the interactive/non-interactive flag was replaced by the
   `CounterpartyNamePrompt` seam, so the core knows nothing about the terminal.
5. The final name goes through `sanitize()`; if it comes out empty, the RFC is used.
6. It is stored in `counterparties` and returned.

On the **web**, step 4 is not a terminal prompt: see §11.

---

## 9. Building the final name

### Template

Default: `{date} {type} {counterparty} - {amount}{credit_note}`

| Placeholder | Contents |
| --- | --- |
| `{date}` | issue date as `YYYY-MM-DD` (cut at the `T`) |
| `{type}` | `I` if your RFC is the issuer (income), `G` if it is the recipient (expense), `?` if it does not appear |
| `{counterparty}` | short name of the other party |
| `{amount}` | total with **exactly two decimals**, no commas and no `$` |
| `{uuid}` | tax folio (UUID) of the stamp, or an empty string |
| `{credit_note}` | `cfdi.credit_note_suffix` (with a leading space) when `TipoDeComprobante == "E"`, empty otherwise |

The placeholder tokens are the strings the user types, and they are stored inside
`settings.value` — renaming one is a breaking change that needs a data migration.
See `.ai/rules/naming.md`.

### Value formatting

- **date:** `explode('T', $date)[0]` + `trim`. If `null`, an empty string.
- **amount:** strip `,` and `$`, cast to float, format with 2 decimals. Use
  `sprintf('%.2F')` and **not** `number_format()`: Python formats the exact binary
  double (banker's rounding) while `number_format` rounds the decimal
  representation up, and they differ on ties (`2.675` → `2.67` vs `2.68`). If the
  value is not numeric, `sanitize()` is applied to the raw value. If `null`, an
  empty string.
- **uuid:** the value as-is, or an empty string.

### Substitution rules

- An **unknown placeholder is left literal** in the output. That is, `{folio}`
  comes out as the text `{folio}`; it neither blows up nor disappears.
  (In Python this is `str.format_map` with a dict implementing `__missing__`.)
- After substitution, **the whole name goes through `sanitize()`**.
- If the result is empty, `"cfdi"` is used.
- The original extension is appended **lowercased**, dot included
  (`.pdf` / `.xml`).

Test cases:

| data | type | counterparty | template | result |
| --- | --- | --- | --- | --- |
| date `2026-02-14T11:32:05`, total `11600.00`, type `I` | `G` | `Contador` | default | `2026-02-14 G Contador - 11600.00.pdf` |
| date `2026-03-01T09:00:00`, total `$1,160.00`, type `E` | `G` | `Cafe Piñón` | default | `2026-03-01 G Cafe Pinon - 1160.00 (credit note).xml` |

The second one is locale-dependent: under `es` the suffix is ` (nota de credito)`,
which is what the app produced before the codebase moved to English.

---

## 10. The orchestrator (`CfdiProcessor`)

It is the core shared by the Artisan command and the web. It must know nothing
about the terminal or HTTP.

### Input

```php
final class ProcessOptions
{
    public function __construct(
        public string $myRfc,
        public string $template,
        public string $outputDirectory,
        public bool $move = false,        // false = copy
    ) {}
}
```

**Built as:** the `interactive` flag was dropped; interaction goes through the
`CounterpartyNamePrompt` passed to `process()`.

### Grouping

Grouping is by **(resolved absolute folder, base name without extension)**. Each
group has an `xml` slot and a `pdf` slot. The order of first appearance is
preserved.

### For each group

1. If there is an XML, try `XmlCfdiParser`. If it throws, keep the reason.
2. If there is no data **and** there is a PDF, try `PdfCfdiParser`. On success,
   **the previous reason is discarded**. On failure, the **first** reason is kept
   (the XML's, if there was one).
3. If there was no data, emit a rejected row **for every file in the group** (XML
   and PDF) with that reason, and **neither of them is copied or moved**.
4. If there was data:
   - Resolve the counterparty (§8).
   - Compute the type letter (§10.1). If it is `?`, add the warning
     `cfdi.warning.rfc_not_a_party`.
   - For **each** file in the group (xml and/or pdf, in that order): build the
     name, resolve the destination collision, copy or move, and emit a row with
     status `Review` when confidence is `Low`, or `Ok` otherwise. The note is
     `cfdi.action.copied` or `cfdi.action.moved`.
   - The CFDI's warnings are accumulated, prefixed with the file name.

### 10.1 Type letter

```
I  if issuerRfc (upper) === myRfc (upper)     // income: I issued it
G  if recipientRfc (upper) === myRfc (upper)  // expense: I received it
?  in any other case
```

### 10.2 Choosing the counterparty

If `issuerRfc === myRfc`, the counterparty is the **recipient** (`recipientRfc`,
`recipientName`). In **any other case** (including `?`), it is the **issuer**.

### 10.3 Name collisions at the destination

If `name.ext` already exists in the output folder, try `name (2).ext`,
`name (3).ext`, … until a free one is found. The suffix goes **before** the
extension and carries a space: `" (2)"`.

### 10.4 File collection (command only)

- Only `.pdf` and `.xml` are accepted (comparing the extension lowercased).
- A file that does not exist → rejected row with `cfdi.collection.missing`.
- A folder passed as a single file → `cfdi.collection.is_directory`.
- Any other extension → `cfdi.collection.unsupported_extension`.
- A `--dir` that is not a folder → `cfdi.collection.not_a_directory`.
- `--dir` walks the folder **alphabetically sorted and non-recursively**.
- Deduplication is by resolved absolute path, preserving order.

### Output

```php
final class ProcessResult
{
    /** @var FileResult[] */
    public array $rows;             // status, original, newName|null, note
    /** @var string[] */
    public array $warnings;
    public int $totalFiles;         // valid files collected
    public int $okFiles;            // actually copied/moved
    public string $outputDirectory; // absolute path
}
```

Possible statuses: `Ok`, `Review`, `Rejected`. Their backing values are `ok`,
`review` and `rejected`; the displayed label comes from `cfdi.status.*`, so under
the `es` locale they still read `OK`, `REVISAR` and `RECHAZADO`.

---

## 11. Web interface

Routes (all without auth, the app is local). **Built as:** the screens became
Livewire full-page components registered with `Route::livewire()`, so the `POST`
endpoints below are component actions instead.

| Method | Route | What it does |
| --- | --- | --- |
| `GET` | `/` | Main screen: dropzone + config status |
| `GET` | `/documents` | The archive of processed CFDIs |
| `GET` | `/settings` | RFC and template form |
| `GET` | `/counterparties` | List of cached counterparties |
| `GET` | `/download/{run}` | Downloads the ZIP with the renamed files (signed) |
| `GET` | `/documents/{document}/download` | Downloads one archived CFDI (signed) |

### Upload flow — two phases

The web has no terminal prompt, so the interactive step of §8.4 is resolved with
an intermediate form:

1. **Phase A (analyze).** The files are uploaded to a temporary directory
   (`storage/app/runs/{uuid}/input`). They are parsed **without copying or
   renaming anything**, and the counterparty RFCs that are **not** in the table
   are collected.
2. If there are new RFCs, a form is shown with one row per RFC: the RFC, the
   legal name from the CFDI, and an input **preloaded with the automatic
   suggestion**. The user can edit each one.
3. **Phase B (process).** Those counterparties are saved and `CfdiProcessor` runs
   with a prompt that asks nothing — by then there is nothing left to ask. The
   output goes to `storage/app/runs/{uuid}/output`.
4. If there were no new RFCs, phase A chains straight into phase B.

On the web:

- It always **copies**, never moves (the originals are temporary uploads).
- The result is shown as an HTML table with the same four columns — `Status`,
  `Original`, `New name`, `Note` — with the status color-coded (`Ok` green,
  `Review` amber, `Rejected` red), the warnings listed underneath, and the
  `X/Y processed successfully.` count.
- A **Download ZIP** button with all the renamed files. Rejected files are **not**
  in the ZIP.
- Upload validation: only `.pdf` and `.xml`, and a reasonable per-file size cap.
  A file with any other extension shows up as a rejected row in the table, not as
  a Laravel validation error.

### Cleanup

Old runs under `storage/app/runs/` are deleted with a `php artisan cfdi:clean`
command (for example, those older than 24 hours). No scheduler is needed.

---

## 12. Artisan command

It must be a 1:1 port of the Python CLI.

```
php artisan cfdi:autofile [files...]
    {--dir=}             Process a whole folder (not recursive)
    {--o|output=}        Output folder (default ./Renamed)
    {--move}             Move instead of copying
    {--no-interactive}   Ask nothing; use the automatic suggestions
    {--rfc=}             Your own RFC for this run (not saved)
    {--template=}        Template for this run (not saved)
    {--set-rfc=}         Save your RFC and exit
    {--set-template=}    Save the default template and exit
```

The flag names are the CLI contract and stay literal; the help text after each
` : ` is translated and lives in `lang/{locale}/console.php`.

### Order of execution

1. If `--set-rfc` is given: save it, print `console.autofile.rfc_saved`, exit **0**.
2. If `--set-template` is given: save it, print `console.autofile.template_saved`,
   exit **0**.
3. If there are no files **and** no `--dir`: print the help and exit **1**.
4. Resolve the RFC: `--rfc` beats the one stored in `settings`.
   - If there is none and it **is** interactive, ask for it
     (`console.autofile.ask_rfc`) and **store it**.
   - If there is none and it is **not** interactive:
     `console.autofile.no_rfc_configured` to **stderr**, exit **1**.
   - If the user answers nothing: `console.autofile.rfc_required` to stderr,
     exit **1**.
5. Resolve the template: `--template` > `settings.template` > default.
6. Resolve the output: `--output` > `cfdi.default_output_directory` (relative to
   the CWD).
7. Collect (§10.4). If no processable file is left: print the error table if there
   is one, then `console.autofile.nothing_to_process`, and exit **1**.
8. Process, print `console.autofile.output_directory`, the table, the warnings and
   the count.

### Exit codes

| Code | When |
| --- | --- |
| `0` | every file was processed successfully |
| `2` | there was at least one rejection (`okFiles !== totalFiles`) |
| `1` | usage error: no arguments, no RFC, or nothing to process |

### The table

Laravel's `$this->table()` is used, with the `Status`, `Original`, `New name` and
`Note` columns. The new name of a rejected file is `—` (em dash).

> The Python has its own table renderer (`render_tabla`) that fits widths to the
> terminal and wraps long cells across several lines. **That is not ported:**
> Symfony Console's table, which Laravel uses, already solves the same problem.
> The corresponding test (`test_cli_imprime_tabla_con_antes_y_despues`) is ported
> only in the part that checks that the headers and the file names show up, not
> the geometry of the borders.

### Warnings

They are printed under a `console.autofile.warnings` heading, one per line, in the
format `  ⚠ {file}: {text}` — two leading spaces. **That format is the contract**;
the wording is translated. See `.ai/rules/commands.md`.

---

## 13. Tests (Pest) — acceptance criteria

The four Python test files are ported. **The suite must pass in full before the
migration can be called done.**

### `tests/Unit/XmlCfdiParserTest.php` (from `test_parser.py`)

- Extracts every field of a CFDI 4.0 (fixture `example_cfdi.xml`): date
  `2026-02-14T11:32:05`, total `11600.00`, type `I`, issuer `AAA010101AAA` /
  `SERVICIOS CONTABLES DEL NORTE SA DE CV`, recipient `BBBB010101BBB` /
  `RECEPTOR DE PRUEBA`, uuid `A1B2C3D4-E5F6-4789-ABCD-1234567890AB`, `replacedUuid`
  null, confidence `High`, `isCreditNote()` false.
- Rejects an XML that is not a CFDI (`not_a_cfdi.xml`) with `not a valid CFDI`.
- Detects a credit note + replacement (`cfdi_credit_note.xml`): type `E`,
  `isCreditNote()` true, `replacedUuid = 11111111-2222-3333-4444-555555555555`.
- **Accepts a CFDI 3.3**: the same fixture with the namespace changed to `.../cfd/3`.
- Rejects when `Fecha` is missing (message contains `the Fecha attribute is missing`).
- Rejects when `Total` is missing (message contains `the Total attribute is missing`).
- Rejects when the Emisor RFC is missing (message contains `Emisor RFC`).

### `tests/Unit/PdfCfdiParserTest.php` (from `test_parser_pdf.py`)

A fake `PdfTextExtractor` returning fixed text is injected — no real PDFs need to
be generated, unlike in the Python.

Base text of a valid CFDI (it stays in Spanish: it stands in for a real invoice
issued in Mexico, and the heuristics match against that wording):

```
FACTURA ELECTRONICA CFDI 4.0
Emisor: SERVICIOS CONTABLES DEL NORTE SA DE CV
RFC Emisor: AAA010101AAA
Receptor: RECEPTOR DE PRUEBA   RFC: BBBB010101BBB
Fecha de emision: 2026-04-05T10:15:00
Folio Fiscal (UUID): 7F8E9D0C-1B2A-4C3D-8E5F-0A1B2C3D4E5F
Subtotal: $2,500.00
IVA 16%: $400.00
Total: $2,900.00
```

- Accepts with confidence `Low`, source `Pdf`, date `2026-04-05`,
  **total `2900.00` (it must not confuse the Subtotal with the Total)**, uuid
  lowercased `7f8e9d0c-1b2a-4c3d-8e5f-0a1b2c3d4e5f`, both RFCs present,
  `invoiceType === null` (nothing is invented), and at least one warning.
- Rejects without extractable text → `no extractable text`.
- Rejects with fewer than two RFCs → `at least 2`.
- Rejects without a UUID or "folio fiscal" → `does not look stamped`.
- Rejects without a total → `could not identify a total`.
- **Accepts the phrase "Folio fiscal: pendiente" without a UUID**: `uuid === null`
  and total `150.00`.

### Naming tests (from `test_naming.py`)

Split across `tests/Unit/CounterpartyNameSuggesterTest.php`,
`tests/Unit/NameSanitizerTest.php`, `tests/Unit/FileNameBuilderTest.php` and
`tests/Feature/CounterpartyResolverTest.php`.

- `suggest` strips the legal form and caps at 3 words (the 3 cases from §8).
- A generic RFC (`XAXX010101000`, `XEXX010101000`) → `General`.
- Non-interactive uses the suggestion **and persists it** in `counterparties`.
- If the RFC is already cached, it **does not ask**, even when interactive.
- Interactive stores the user's answer.
- `build` with the default template → `2026-02-14 G Contador - 11600.00.pdf`.
- Credit note + sanitizing → `2026-03-01 G Cafe Pinon - 1160.00 (credit note).xml`,
  and the result contains no `,`, `$` or `ñ`.
- The same case under `App::setLocale('es')` still yields ` (nota de credito)`.
- A custom template with `{uuid}`.

### `tests/Feature/CfdiAutofileCommandTest.php` (from `test_cli.py`)

Each test runs against an in-memory SQLite database and a temporary directory.

- Copies a valid CFDI, leaves the original untouched, exit code **0**, prints
  `1/1 processed successfully.`, and creates
  `2026-02-14 G SERVICIOS CONTABLES NORTE - 11600.00.xml`.
- Rejects an XML that is not a CFDI: exit code **2**, prints `REJECTED` and
  `not a valid CFDI`, `0/1 processed successfully.`, the output folder is left
  **empty**, and the original is still there.
- `--move` takes the original out of the input folder.
- Two copies of the same CFDI produce `… - 11600.00.xml` and `… - 11600.00 (2).xml`.
- `--set-rfc bbbb010101bbb` stores `BBBB010101BBB` (uppercased) and
  `--set-template` persists the template.
- Without `--output`, the output lands in `./Renamed`.
- The summary table includes all four headers and the names of both files.

### `tests/Feature/Web/` — new, specific to the web

These do not exist in the Python. They are the minimum for calling the web good:

- Processing a CFDI whose counterparty is **already cached** goes straight through
  and returns the results table.
- A **new** counterparty returns the intermediate form with the suggestion
  preloaded, and **has not yet** written anything into the output folder.
- Confirming the counterparties stores the name the user edited and produces the
  file under that name.
- A rejected file does **not** appear in the download ZIP.
- Saving the settings stores the RFC uppercased.

### Fixtures

Copied as-is from the Python's `tests/fixtures/`, renamed to English:
`example_cfdi.xml`, `cfdi_credit_note.xml`, `not_a_cfdi.xml`,
`cfdi_generic_rfc.xml`. Their XML bodies are unchanged.

---

## 14. Suggested implementation order

1. `laravel new` with Herd, SQLite, Pest. Copy the fixtures.
2. Migrations + `Setting` and `Counterparty` models + the `AppSettings` helper.
3. `CfdiData`, `CfdiRejectedException`, `XmlCfdiParser` + its test. **Green.**
4. `PdfTextExtractor` (interface + fake + real), `PdfCfdiParser` + its test. **Green.**
5. `NameSanitizer`, `CounterpartyNameSuggester`, `CounterpartyResolver`,
   `FileNameBuilder` + their tests. **Green.**
6. `FileGrouper` + `CfdiProcessor`.
7. `CfdiAutofileCommand` + its feature test. **Green.**
8. Web: settings → two-phase upload → table → ZIP → counterparties.
9. `php artisan cfdi:clean`.

Steps 3 to 7 are pure parity with the Python and can be verified against it by
running both side by side over the same files.

---

## 15. Out of scope

Do not implement, not even "just in case":

- Login, roles, multi-user.
- Digital-seal validation or SAT webservice lookups.
- OCR for scanned PDFs.
- Folder watchers, queues, background jobs, scheduler.
- Google Drive, Sheets, Dropbox or accounting integrations.
- A public API.
