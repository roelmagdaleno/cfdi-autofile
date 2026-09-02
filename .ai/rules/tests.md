---
paths:
  - 'tests/**'
---

# Tests

## El disco `documents` se finge globalmente en tests/Pest.php
`DocumentArchiver` copia cada CFDI procesado al disco `documents` (storage/app/cfdis). Cualquier test que procese un CFDI lo dispara, asi que `tests/Pest.php` hace `Storage::fake(Document::DISK)` en un `beforeEach` global para todo `Feature`.

Sin ese fake los tests escriben en el storage real, y como `RefreshDatabase` si reinicia la base pero el disco no, la deduplicacion por folio no encuentra la fila previa y el archivo se va llenando de duplicados ` (2)`, ` (3)`, ... No quites ese fake ni lo reemplaces por uno por archivo.
