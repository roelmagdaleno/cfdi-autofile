# cfdi-autofile

Renames CFDIs (PDF + XML) using a configurable template, archives them, and — optionally — uploads them to Google Drive.

A CFDI arrives from the SAT as `A1B2C3D4-....pdf` plus its `.xml` twin. This app reads the invoice, works out who the counterparty is and whether it is income or an expense, and turns both files into something you can actually find later:

```
2026-02-14 G ACME SA DE CV - 1160.00.pdf
2026-02-14 G ACME SA DE CV - 1160.00.xml
```

It runs two ways over the same core: a web UI (Livewire + Flux) and an Artisan command.

## Requirements

- PHP 8.3+ with `dom`, `intl`, `libxml`, `mbstring` and `zip`
- Composer and Node 20+
- SQLite (the default database is a local file)

## Install

```bash
composer setup
```

That copies `.env.example` to `.env`, generates the key, creates `database/database.sqlite`, migrates, and builds the frontend.

Then start it:

```bash
composer dev
```

## How it names files

The name comes from a template stored in the `settings` table. The default is:

```
{date} {type} {counterparty} - {amount}{credit_note}
```

| Placeholder      | Value                                                              |
| ---------------- | ------------------------------------------------------------------ |
| `{date}`         | Issue date as `YYYY-MM-DD`                                          |
| `{type}`         | Income or expense label — `I` / `G` by default, configurable        |
| `{counterparty}` | The other party's name, sanitized                                   |
| `{amount}`       | Total with two decimals                                            |
| `{uuid}`         | Fiscal folio                                                       |
| `{credit_note}`  | Suffix appended only to credit notes                                |

Anything else in the template is written literally, so `{folio}` comes out as `{folio}` rather than blowing up.

Income vs. expense is decided by your own RFC: if you are the issuer it is income, if you are the receiver it is an expense. When your RFC appears on neither side the file is still named, flagged with a warning, and the type letter becomes `-`.

The XML is parsed first; a PDF-only CFDI falls back to reading the text of the PDF by heuristics.

### Counterparties

Legal names on a CFDI are long and noisy, so each RFC gets a short name you choose once and reuse forever (the `counterparties` table). The app suggests one; on the CLI it asks interactively, in the browser it asks in a confirmation step before writing anything. `--no-interactive` accepts every suggestion. Edit them later at **Counterparties**.

## The web UI

| Route            | Screen                                                                     |
| ---------------- | -------------------------------------------------------------------------- |
| `/`              | Upload CFDIs, review the names, download the run as a ZIP                   |
| `/documents`     | Browse and search the archive, download an individual document              |
| `/counterparties`| Rename or delete saved counterparties                                       |
| `/settings`      | RFC, template, type letters, and the cloud integration                      |

Uploads for a run live on the `runs` disk and are meant to be temporary; the archived copy on the `documents` disk (`{RFC}/{YYYY}/{name}.{ext}`) is the one that stays. Clean up the run directories whenever you like:

```bash
php artisan cfdi:clean --hours=24   # 0 cleans everything
```

It never touches the archive or the history — the `runs` row remains, only its files go.

## The command

```bash
php artisan cfdi:autofile invoice.pdf invoice.xml
php artisan cfdi:autofile --dir=~/Downloads/cfdis --output=~/Documents/CFDIs --move
```

| Option                    | What it does                                                    |
| ------------------------- | --------------------------------------------------------------- |
| `--dir=`                  | Process every PDF/XML in a directory                             |
| `-o, --output=`           | Where the renamed files go (created if missing)                  |
| `--move`                  | Move instead of copy                                             |
| `--no-interactive`        | Never ask; accept the suggested counterparty names               |
| `--rfc=`, `--template=`   | Override the saved settings for this run only                    |
| `--letter-income=`, `--letter-expense=` | Override the `{type}` labels for this run only     |
| `--set-*`                 | Save an RFC, template, or letter as the new default and exit     |

Exit codes: `0` everything fine, `1` bad input or nothing to process, `2` processed with at least one failure.

Unlike the web, the command does not copy files into the archive — the file already lives where `--output` asked, so only its path is indexed.

## Google Drive (optional)

Off by default; nothing reaches the network until you turn it on in **Settings**. When enabled, every processed file is also uploaded to a folder named from a `{year}` template — `{year} - Emitidas` for income, `{year} - Recibidas` for expenses — under a Drive folder you pick.

Set up an OAuth client and fill in:

```dotenv
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=http://localhost:8000/drive/callback
```

Google refuses to register a redirect URI that is not on a public TLD, so a `.test` domain cannot be used for the handshake. Do the one-time connect over `php artisan serve` at `http://localhost:8000`; the refresh token is stored encrypted in the `settings` table, and after that the integration works from your usual host.

Uploading runs last and can never fail a run: the files are already renamed and archived, so a failed upload comes back as a row you can retry from the UI.

## Localization

The app ships with English and Spanish (`lang/en`, `lang/es`). Switch with `APP_LOCALE=es`. A few translated strings reach the disk rather than the screen — the credit-note suffix and the default output directory — so the locale changes what gets written.

## Development

```bash
composer test          # config clear + Pint check + PHPStan + Pest
composer lint          # Pint
composer types:check   # PHPStan (larastan)
php artisan test --compact
```

Project conventions for agents and contributors live in `.ai/rules/`, indexed by path in `.ai/rules/index.md`.

## License

MIT.
