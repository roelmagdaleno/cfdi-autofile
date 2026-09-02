---
paths:
  - 'app/Cfdi/Naming/**'
---

# Naming

## El catalogo de placeholders vive en el enum Placeholder
Agregar o quitar un placeholder de la plantilla del nombre se hace SOLO en `App\Cfdi\Naming\Placeholder`: de ahi salen las claves que sustituye `FileNameBuilder`, el patron de validacion (`Placeholder::pattern()`) y los botones de la pantalla de ajustes. No vuelvas a escribir la lista a mano en ninguno de esos tres lugares.
