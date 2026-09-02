---
paths:
  - 'app/Console/Commands/**'
---

# Commands

## laravel/pao compacts console output in agent sessions
`laravel/pao` (dev) replaces `OutputStyle` with `PaoOutputStyle`, which collapses consecutive spaces and strips glyphs like `⚠` along with table padding. It only kicks in when it detects an agent (Claude Code, Cursor, …) and skips tests.

Consequence: the command output you see as an agent is NOT the real bytes. Before reporting a formatting difference as a bug, re-run with `PAO_DISABLE=1`. Tests still see the exact bytes because Pao does not touch `runningUnitTests()`.

This command is a 1:1 port of a Python CLI. The contract is the exact output **format** (`  ⚠ {file}: {text}`, two leading spaces), not the language: the wording is translated and lives in `lang/{locale}/console.php`, and `lang/es` reproduces the original Spanish strings verbatim.

## The signature and description are built in the constructor
`__()` is not a constant expression, so `$signature` and `$description` cannot be property defaults. Assign them in `__construct()` *before* `parent::__construct()`, which is where Symfony parses the signature. The flag names themselves (`--dir`, `--set-rfc`, …) stay literal — they are the CLI contract; only the help text after each ` : ` is translated.
