---
paths:
  - 'app/Console/Commands/**'
---

# Commands

## laravel/pao compacta la salida de consola en sesiones de agente
`laravel/pao` (dev) reemplaza `OutputStyle` por `PaoOutputStyle`, que colapsa espacios consecutivos y quita glifos como `⚠` y el padding de las tablas. Solo se activa cuando detecta un agente (Claude Code, Cursor, …) y se salta los tests.

Consecuencia: la salida del comando que ves desde un agente NO son los bytes reales. Antes de reportar una diferencia de formato como bug, vuelve a correr con `PAO_DISABLE=1`. Los tests siguen viendo los bytes exactos porque Pao no toca `runningUnitTests()`.

Este comando es un port 1:1 de un CLI de Python y el formato exacto (`  ⚠ {archivo}: {texto}`, dos espacios) es parte del contrato.
