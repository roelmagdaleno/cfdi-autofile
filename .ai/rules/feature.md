---
paths:
  - 'tests/Feature/**'
---

# Feature

## expectsOutputToContain: one substring per write
`expectsOutputToContain()` sets up a Mockery expectation on each `doWrite` call, and the first matching expectation consumes that write. Two assertions that land on the SAME physical line (for example two texts from the same `$this->table()` row) cannot both match: the second one fails with "Output does not contain …".

For assertions about a table's contents use `Artisan::call()` + `Artisan::output()` and `expect($output)->toContain(...)`. See the `runAutofile()` helper in `tests/Feature/CfdiAutofileCommandTest.php`.
