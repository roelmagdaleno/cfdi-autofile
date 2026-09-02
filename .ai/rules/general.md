---
paths:
  - '**/*.php'
---

# General

## No else branches — use guard clauses
Do not write `else` or `elseif`. They bury the happy path and make a method harder to read as it grows.

- Invert the condition and leave early: `return`, `continue`, or `throw` for the rejected case, then let the main body sit unindented.
- Multi-way dispatch on one value: use `match`, not an `if`/`elseif` chain.
- Value fallback chains: use `??` (or a small private helper with one early return per candidate) instead of `elseif` assignments.
