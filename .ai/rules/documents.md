---
paths:
  - 'app/Support/Documents/**'
---

# Documents

## La web copia al archivo; el comando solo indexa la ruta
`DocumentArchiver::archive()` tiene dos modos y la diferencia no es cosmetica:

- Web (`copyIntoArchive: true`): los originales son subidas temporales que `cfdi:clean` borra, asi que la copia en el disco `documents` es la unica que va a quedar.
- Comando (`copyIntoArchive: false`): el archivo ya vive donde lo pidio `--output`. Copiarlo otra vez duplicaria cada byte sin ganar nada, asi que solo se indexa la ruta absoluta y `documents.disk` queda en `null`. El costo aceptado es que el usuario puede mover o borrar ese archivo desde afuera; por eso la UI verifica `fileExists()` antes de ofrecer la descarga.

La llave de deduplicacion es (`folio`, `extension`); cuando no hay folio (CFDI leido del PDF por heuristica) el fallback es (`checksum`, `extension`).
