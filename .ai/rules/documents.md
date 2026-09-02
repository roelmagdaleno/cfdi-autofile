---
paths:
  - 'app/Support/Documents/**'
---

# Documents

## The web copies into the archive; the command only indexes the path
`DocumentArchiver::archive()` has two modes and the difference is not cosmetic:

- Web (`copyIntoArchive: true`): the originals are temporary uploads that `cfdi:clean` deletes, so the copy on the `documents` disk is the only one that will remain.
- Command (`copyIntoArchive: false`): the file already lives where `--output` asked for it. Copying it again would duplicate every byte for nothing, so only the absolute path is indexed and `documents.disk` stays `null`. The accepted cost is that the user can move or delete that file from outside; that is why the UI checks `fileExists()` before offering the download.

The deduplication key is (`folio`, `extension`); when there is no folio (a CFDI read from the PDF by heuristics) the fallback is (`checksum`, `extension`).
