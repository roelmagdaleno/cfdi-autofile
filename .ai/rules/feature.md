---
paths:
  - 'tests/Feature/**'
---

# Feature

## expectsOutputToContain: un substring por escritura
`expectsOutputToContain()` monta una expectativa de Mockery sobre cada llamada a `doWrite`, y la primera expectativa que matchea consume esa escritura. Dos aserciones que caen en la MISMA linea fisica (por ejemplo dos textos de la misma fila de `$this->table()`) no pueden matchear las dos: la segunda falla con "Output does not contain ...".

Para aserciones sobre el contenido de una tabla usa `Artisan::call()` + `Artisan::output()` y `expect($output)->toContain(...)`. Ver el helper `runAutofile()` en `tests/Feature/CfdiAutofileCommandTest.php`.
