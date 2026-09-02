# cfdi-autofile — Especificación para la migración a Laravel

Documento de referencia para reimplementar el CLI de Python `cfdi-autofile` como
una aplicación Laravel. Está escrito para que Claude Code lo pueda ejecutar
dentro de un proyecto Laravel recién creado con Herd, sin tener que leer el
código de Python.

**Fuente de verdad del comportamiento:** el proyecto Python en
`/Users/roelmagdaleno/Code/Roel/cfdi-autofile` (`src/cfdi_autofile/` y `tests/`).
Cuando este documento y el código de Python difieran, gana el código de Python.

---

## 1. Qué hace el producto

Renombra CFDIs (comprobantes fiscales mexicanos: PDF + XML) según una plantilla
configurable, y **rechaza** los archivos que no son CFDIs reales.

Alcance deliberadamente chico: **solo** renombrado + validación. No hay watcher
de carpetas, ni Google Drive/Sheets, ni integración con el SAT, ni nada
corriendo en segundo plano.

Reglas de producto que no se negocian:

1. **Nunca se inventa un dato.** Lo que no se pudo extraer con certeza queda en
   `null` y el archivo se marca para revisión o se rechaza.
2. **Los archivos rechazados nunca se copian ni se mueven.** Se quedan donde
   estaban.
3. **El XML es la fuente de verdad.** El PDF es solo un fallback de confianza
   baja cuando no hay XML.
4. **PDF + XML con el mismo nombre base = una sola factura.** Los dos terminan
   con el mismo nombre nuevo.

---

## 2. Decisiones de arquitectura ya tomadas

| Decisión | Valor |
| --- | --- |
| Framework | Laravel 12, PHP 8.4 (Herd) |
| Interfaces | **Dos**: app web + comando Artisan, ambas sobre un mismo core de servicios |
| Persistencia | **SQLite** (`database/database.sqlite`) para settings y contrapartes |
| Usuarios | **Un solo usuario, sin login.** Corre local en Herd. Sin `users`, sin Breeze, sin Fortify |
| Paridad | **Exacta** con el Python, incluidos los mensajes de rechazo en español. Los 4 archivos de test se portan a Pest |
| Frontend | Blade + Tailwind + Alpine.js (o Livewire si se prefiere). Sin SPA, sin build complejo |

### Dependencias de Composer

| Paquete | Para qué |
| --- | --- |
| `smalot/pdfparser` | Extraer texto de PDFs. Es el equivalente de `pdfplumber` |
| `ext-dom` / `ext-libxml` | Parseo del XML (viene con PHP) |
| `ext-zip` | Empaquetar la descarga de la web |
| `ext-intl` | Normalización Unicode para quitar acentos |
| `pestphp/pest` (dev) | Tests |

> **Nota sobre `smalot/pdfparser`:** no es idéntico a `pdfplumber` en cómo
> ordena el texto. La extracción debe estar aislada detrás de una interfaz
> (`PdfTextExtractor`) para poder cambiar de librería sin tocar la lógica de
> validación, y los tests deben poder inyectar texto directamente.

---

## 3. Estructura de archivos objetivo

```
app/
├── Cfdi/
│   ├── Data/
│   │   ├── CfdiData.php            # DTO readonly con los datos extraídos
│   │   └── ResultadoArchivo.php    # DTO de una fila del resumen
│   ├── Exceptions/
│   │   └── CfdiRechazadoException.php
│   ├── Parsing/
│   │   ├── XmlCfdiParser.php       # fuente de verdad, confianza alta
│   │   ├── PdfCfdiParser.php       # fallback, confianza baja
│   │   ├── PdfTextExtractor.php    # interfaz
│   │   └── SmalotPdfTextExtractor.php
│   ├── Naming/
│   │   ├── Sanitizador.php         # quitarAcentos / sanitizar
│   │   ├── SugeridorDeNombre.php   # razón social -> nombre corto
│   │   ├── ResolvedorDeContraparte.php
│   │   └── ConstructorDeNombre.php # aplica la plantilla
│   ├── ProcesadorDeCfdis.php       # orquestador: agrupa, valida, renombra
│   └── AgrupadorDeArchivos.php     # PDF+XML por stem
├── Console/Commands/
│   └── CfdiAutofileCommand.php     # php artisan cfdi:autofile
├── Http/Controllers/
│   ├── ProcesamientoController.php
│   ├── ContraparteController.php
│   └── AjustesController.php
├── Models/
│   ├── Ajuste.php
│   └── Contraparte.php
└── Support/
    └── Ajustes.php                 # fachada tipada sobre la tabla ajustes
```

---

## 4. Modelo de datos (SQLite)

### `ajustes` (key-value, una sola fila por clave)

```php
Schema::create('ajustes', function (Blueprint $table) {
    $table->id();
    $table->string('clave')->unique();
    $table->text('valor')->nullable();
    $table->timestamps();
});
```

Claves usadas:

| Clave | Contenido | Default |
| --- | --- | --- |
| `rfc` | RFC propio, siempre guardado en MAYÚSCULAS y sin espacios | `null` |
| `plantilla` | Plantilla de nombre | `{fecha} {tipo} {contraparte} - {monto}{nota_credito}` |

El helper `App\Support\Ajustes` expone `rfc()`, `setRfc(string)`,
`plantilla()`, `setPlantilla(string)`. `setRfc` aplica `trim` + `strtoupper`.

### `contrapartes` (cache de RFC -> nombre corto)

```php
Schema::create('contrapartes', function (Blueprint $table) {
    $table->id();
    $table->string('rfc')->unique();
    $table->string('nombre');
    $table->string('razon_social')->nullable(); // informativo, la del CFDI
    $table->timestamps();
});
```

`rfc` siempre en MAYÚSCULAS. `nombre` siempre pasa por `Sanitizador::sanitizar()`
antes de guardarse.

> No se crea tabla de historial ni de facturas procesadas. Está fuera de alcance.

---

## 5. El DTO `CfdiData`

```php
final class CfdiData
{
    public function __construct(
        public ?string $fecha = null,             // ISO tal cual viene: "2026-02-14T11:32:05"
        public ?string $total = null,             // string crudo: "11600.00"
        public ?string $tipoComprobante = null,   // "I" | "E" | "N" | ... | null
        public ?string $emisorRfc = null,
        public ?string $emisorNombre = null,
        public ?string $receptorRfc = null,
        public ?string $receptorNombre = null,
        public ?string $uuid = null,
        public ?string $uuidSustituido = null,
        public string $confianza = 'alta',        // 'alta' (xml) | 'baja' (pdf)
        public ?string $fuente = null,            // 'xml' | 'pdf'
        public array $advertencias = [],
    ) {}

    public function esNotaCredito(): bool
    {
        return $this->tipoComprobante === 'E';
    }
}
```

---

## 6. Parseo y validación de XML (`XmlCfdiParser`)

Confianza `alta`, fuente `xml`.

### Namespaces

```php
const NS_CFDI_3 = 'http://www.sat.gob.mx/cfd/3';
const NS_CFDI_4 = 'http://www.sat.gob.mx/cfd/4';
const NS_TFD    = 'http://www.sat.gob.mx/TimbreFiscalDigital';
```

### Algoritmo

1. Cargar el XML. Si está mal formado, lanzar
   `CfdiRechazadoException("Este XML no es un CFDI valido: XML mal formado (…).")`.
   Usar `libxml_use_internal_errors(true)` y no dejar que PHP emita warnings.
2. **El nodo raíz debe llamarse `Comprobante`** (nombre local, ignorando el
   prefijo) **y estar en el namespace** `NS_CFDI_3` o `NS_CFDI_4`. Si no:
   `"Este XML no es un CFDI valido: el nodo raiz no es cfdi:Comprobante en el namespace de CFDI 3.3 o 4.0."`
3. Leer del nodo raíz los atributos `Fecha`, `Total`, `TipoDeComprobante`.
4. Buscar el **hijo directo** con nombre local `Emisor` → atributos `Rfc`, `Nombre`.
5. Buscar el **hijo directo** con nombre local `Receptor` → atributos `Rfc`, `Nombre`.
6. Dentro del hijo directo `Complemento`, recorrer **todos los descendientes**
   buscando el primero cuyo nombre local sea `TimbreFiscalDigital` **y** cuyo
   namespace sea `NS_TFD`. De ahí sale `uuid` (atributo `UUID`, **tal cual, sin
   cambiar mayúsculas**).
7. Si existe el hijo directo `CfdiRelacionados` **con** `TipoRelacion="04"`,
   tomar el `UUID` de su hijo `CfdiRelacionado` → `uuidSustituido`, y **agregar
   una advertencia** (no se rechaza):
   `"sustituye al CFDI {uuid} (TipoRelacion 04)"`.

### Rechazos, en este orden exacto

| Condición | Mensaje |
| --- | --- |
| falta `Fecha` | `CFDI incompleto: falta el atributo Fecha en el Comprobante.` |
| falta `Total` | `CFDI incompleto: falta el atributo Total en el Comprobante.` |
| falta RFC del Emisor | `CFDI incompleto: falta el RFC del Emisor.` |
| falta RFC del Receptor | `CFDI incompleto: falta el RFC del Receptor.` |

> Nota: la búsqueda por nombre local + namespace es intencional. El prefijo del
> namespace (`cfdi:`, `c:`, ninguno) **no** debe importar. Con `DOMDocument`,
> comparar `$nodo->localName` y `$nodo->namespaceURI`.

---

## 7. Parseo y validación de PDF (`PdfCfdiParser`)

Confianza `baja`, fuente `pdf`. Solo se usa **cuando no hay XML** o cuando el XML
fue rechazado.

### Expresiones regulares (portadas literalmente, con `/u`)

```php
// RFC mexicano: 3 letras (moral) o 4 (física) + 6 dígitos de fecha + 3 de homoclave
const RFC_RE = '/\b([A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3})\b/u';

const UUID_RE = '/\b[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\b/';

// El (?<!sub) evita que "Subtotal: $2,500.00" se confunda con el total
const TOTAL_RE = '/(?<!sub)(?<![a-z])(?:gran\s+total|importe\s+total|total)\b\D{0,20}?\$?\s*'
               . '(\d{1,3}(?:,\d{3})*(?:\.\d{2})?|\d+\.\d{2})/i';

const FECHA_ISO_RE = '/\b(\d{4}-\d{2}-\d{2})(?:[T ]\d{2}:\d{2}:\d{2})?\b/';
const FECHA_DMY_RE = '/\b(\d{2})\/(\d{2})\/(\d{4})\b/';
```

### Checklist de validación — el primero que falla es el motivo del rechazo

1. **¿Hay texto extraíble?** Si el texto extraído está vacío o solo son espacios:
   `"Rechazado: el PDF no tiene texto extraible (probablemente es un escaneo o imagen). Consigue el XML o un PDF con texto."`
2. **¿Hay al menos 2 RFCs distintos con formato válido?** Se busca sobre el texto
   en MAYÚSCULAS, preservando el orden de aparición y **eliminando duplicados**.
   Si hay menos de 2:
   `"Rechazado: se encontraron {n} RFC(s) con formato valido y un CFDI necesita al menos 2 (emisor y receptor)."`
3. **¿Está timbrado?** Debe aparecer un UUID con guiones **o** la frase
   `folio fiscal` (case-insensitive). Si no:
   `"Rechazado: no aparece un UUID ni la frase 'folio fiscal'; el documento no parece estar timbrado ante el SAT."`
4. **¿Hay un total identificable?** Se toma la **última** coincidencia de
   `TOTAL_RE` en el texto, no la primera — el total suele venir al final del
   documento. Si no hay ninguna:
   `"Rechazado: no se pudo identificar un total en el texto del PDF."`
5. **¿Hay fecha?** Se intenta primero `FECHA_ISO_RE`; si no, `FECHA_DMY_RE`
   (`dd/mm/yyyy` → `yyyy-mm-dd`). Si no hay ninguna:
   `"Rechazado: no se pudo identificar la fecha de emision en el texto del PDF."`

### Si pasa todo

- `total` = la captura, **sin comas**.
- `uuid` = el UUID encontrado **en minúsculas**, o `null` si solo hubo la frase
  "folio fiscal".
- `emisorRfc` = primer RFC en orden de aparición, `receptorRfc` = segundo.
  **Es una heurística y puede estar invertida.**
- `tipoComprobante` = `null`. **Nunca se adivina.**
- Se agrega siempre esta advertencia:
  `"datos extraidos del PDF por heuristica; el orden emisor/receptor puede estar invertido y no se pudo determinar el tipo de comprobante"`

---

## 8. Nombres de contraparte

### `Sanitizador::quitarAcentos(string): string`

1. Reemplaza `ñ` → `n` y `Ñ` → `N` **antes** de normalizar.
2. Normaliza a NFD y elimina todas las marcas diacríticas (categoría Unicode
   `Mn`). Con `intl`: `Normalizer::normalize($t, Normalizer::FORM_D)` y luego
   `preg_replace('/\p{Mn}/u', '', $t)`.

### `Sanitizador::sanitizar(string): string`

En este orden:

1. `quitarAcentos`
2. Elimina `,` y `$`
3. Reemplaza `/ \ : * ? " < > |` por `-`
4. Colapsa runs de espacios en blanco a un solo espacio
5. `trim` de espacios, y luego `trim` de puntos en ambos extremos

### `SugeridorDeNombre::sugerir(?string $razonSocial): string`

Razón social sin forma societaria, máximo 3 palabras, sin acentos.

1. Si viene vacía o `null`, regresa `''`.
2. `quitarAcentos`.
3. Reemplaza por espacio todo lo que **no** sea letra/dígito/`_`/espacio/`&`/`.`
   (`preg_replace('/[^\w\s&.]/u', ' ', $t)`).
4. Para cada palabra: su forma base es la palabra **sin puntos y en MAYÚSCULAS**.
   Si la base está vacía o está en `FORMAS_SOCIETARIAS`, se descarta. Si no, se
   conserva la palabra original **sin los puntos de los extremos**.
5. Si no quedó ninguna palabra, se usan todas las palabras originales.
6. Regresa las primeras **3** palabras unidas por espacio, con `trim`.

#### `FORMAS_SOCIETARIAS` (set exacto, comparado en MAYÚSCULAS)

```
SA, S, A, DE, CV, C, V, SAPI, SAB, SC, AC, SRL, RL, SDE, S.A., S.A, SAS,
SOFOM, ENR, MI, SPR, SNC, SAPIB, B, BURSATIL, CAPITAL, VARIABLE, ANONIMA,
SOCIEDAD, PROMOTORA, INVERSION, RESPONSABILIDAD, LIMITADA, CIVIL, LTD,
LLC, INC, CORP,
DEL, LA, LAS, LOS, EL, Y, E
```

Los últimos son conectores que no aportan al nombre corto.

Casos de prueba:

| Entrada | Salida |
| --- | --- |
| `SERVICIOS CONTABLES DEL NORTE SA DE CV` | `SERVICIOS CONTABLES NORTE` |
| `Café Piñón S.A. de C.V.` | `Cafe Pinon` |
| `TELEFONOS DE MEXICO SAB DE CV` | `TELEFONOS MEXICO` |

### `ResolvedorDeContraparte::resolver(string $rfc, ?string $razonSocial, bool $interactivo): string`

RFC se normaliza con `trim` + `strtoupper` al entrar.

1. Si el RFC es **genérico** (`XAXX010101000` o `XEXX010101000`), regresa
   `"General"` **sin tocar la base de datos**.
2. Si el RFC ya está en la tabla `contrapartes` con un `nombre` no vacío, lo usa
   tal cual y **no pregunta nada**.
3. Calcula `$sugerencia = SugeridorDeNombre::sugerir($razonSocial) ?: $rfc`.
4. **Modo interactivo:** pide al usuario el nombre mostrando la sugerencia como
   default. Si el usuario responde vacío (o cancela), se usa la sugerencia.
   **Modo no interactivo:** usa la sugerencia sin preguntar.
5. El nombre final pasa por `sanitizar()`; si queda vacío, se usa el RFC.
6. Se guarda en `contrapartes` y se regresa.

En la **web** el paso 4 no es un prompt de terminal: ver §11.

---

## 9. Construcción del nombre final

### Plantilla

Default: `{fecha} {tipo} {contraparte} - {monto}{nota_credito}`

| Placeholder | Contenido |
| --- | --- |
| `{fecha}` | fecha de emisión en `YYYY-MM-DD` (se corta en la `T`) |
| `{tipo}` | `I` si tu RFC es el emisor (ingreso), `G` si es el receptor (gasto), `?` si no aparece |
| `{contraparte}` | nombre corto de la otra parte |
| `{monto}` | total con **exactamente dos decimales**, sin comas ni `$` |
| `{uuid}` | folio fiscal (UUID) del timbre, o cadena vacía |
| `{nota_credito}` | `" (nota de credito)"` (con espacio inicial) si `TipoDeComprobante == "E"`, vacío si no |

### Formateo de valores

- **fecha:** `explode('T', $fecha)[0]` + `trim`. Si es `null`, cadena vacía.
- **monto:** quita `,` y `$`, castea a float, formatea con 2 decimales
  (`number_format($v, 2, '.', '')`). Si no es numérico, aplica `sanitizar()` al
  valor crudo. Si es `null`, cadena vacía.
- **uuid:** el valor tal cual o cadena vacía.

### Reglas de sustitución

- Un placeholder **desconocido se deja literal** en la salida. Es decir,
  `{folio}` queda como el texto `{folio}`, no revienta ni desaparece.
  (En Python es `str.format_map` con un dict que implementa `__missing__`.)
- Después de sustituir, **todo el nombre pasa por `sanitizar()`**.
- Si el resultado queda vacío, se usa `"cfdi"`.
- Se le concatena la extensión original **en minúsculas**, incluyendo el punto
  (`.pdf` / `.xml`).

Casos de prueba:

| datos | tipo | contraparte | plantilla | resultado |
| --- | --- | --- | --- | --- |
| fecha `2026-02-14T11:32:05`, total `11600.00`, tipo `I` | `G` | `Contador` | default | `2026-02-14 G Contador - 11600.00.pdf` |
| fecha `2026-03-01T09:00:00`, total `$1,160.00`, tipo `E` | `G` | `Cafe Piñón` | default | `2026-03-01 G Cafe Pinon - 1160.00 (nota de credito).xml` |

---

## 10. El orquestador (`ProcesadorDeCfdis`)

Es el corazón compartido por el comando Artisan y la web. No debe conocer ni la
terminal ni HTTP.

### Entrada

```php
final class OpcionesDeProceso
{
    public function __construct(
        public string $miRfc,
        public string $plantilla,
        public string $carpetaSalida,
        public bool $mover = false,        // false = copia
        public bool $interactivo = true,
    ) {}
}
```

### Agrupación

Se agrupa por **(carpeta absoluta resuelta, nombre base sin extensión)**. Cada
grupo tiene un slot `xml` y un slot `pdf`. Se preserva el orden de primera
aparición.

### Por cada grupo

1. Si hay XML, se intenta `XmlCfdiParser`. Si lanza, se guarda el motivo.
2. Si no hubo datos **y** hay PDF, se intenta `PdfCfdiParser`. Si tiene éxito,
   **se limpia el motivo anterior**. Si falla, se conserva el **primer** motivo
   (el del XML si lo hubo).
3. Si no hubo datos, se emite una fila `RECHAZADO` **por cada archivo del grupo**
   (XML y PDF), con ese motivo, y **ninguno de los dos se copia ni se mueve**.
4. Si hubo datos:
   - Se resuelve la contraparte (§8).
   - Se calcula la letra de tipo (§10.1). Si es `?`, se agrega la advertencia
     `"tu RFC {MI_RFC} no aparece como emisor ni receptor"`.
   - Para **cada** archivo del grupo (xml y/o pdf, en ese orden): se construye el
     nombre, se resuelve la colisión de destino, se copia o mueve, y se emite una
     fila con estado `REVISAR` si `confianza === 'baja'`, o `OK` si no. La nota es
     `"copiado"` o `"movido"`.
   - Las advertencias del CFDI se acumulan prefijadas con el nombre del archivo.

### 10.1 Letra de tipo

```
I  si emisorRfc (upper) === miRfc (upper)   // ingreso: yo emití
G  si receptorRfc (upper) === miRfc (upper) // gasto: yo recibí
?  en cualquier otro caso
```

### 10.2 Elección de la contraparte

Si `emisorRfc === miRfc`, la contraparte es el **receptor** (`receptorRfc`,
`receptorNombre`). En **cualquier otro caso** (incluido `?`), es el **emisor**.

### 10.3 Colisión de nombres en el destino

Si `nombre.ext` ya existe en la carpeta de salida, se prueba
`nombre (2).ext`, `nombre (3).ext`, … hasta encontrar uno libre.
El sufijo va **antes** de la extensión y lleva espacio: `" (2)"`.

### 10.4 Recolección de archivos (solo aplica al comando)

- Se aceptan **solo** `.pdf` y `.xml` (comparando la extensión en minúsculas).
- Un archivo que no existe → fila `RECHAZADO` con `"no existe."`.
- Una carpeta pasada como archivo suelto → `"es una carpeta (usa --dir)."`.
- Otra extensión → `"extension no soportada (solo .pdf y .xml)."`.
- `--dir` que no es carpeta → `"--dir {ruta}: no es una carpeta."`.
- `--dir` recorre la carpeta **ordenada alfabéticamente y no recursiva**.
- Se deduplica por ruta absoluta resuelta, preservando el orden.

### Salida

```php
final class ResultadoDeProceso
{
    /** @var ResultadoArchivo[] */
    public array $filas;          // estado, original, nuevoNombre|null, nota
    /** @var string[] */
    public array $advertencias;
    public int $totalArchivos;    // archivos válidos recolectados
    public int $okArchivos;       // efectivamente copiados/movidos
    public string $carpetaSalida; // ruta absoluta
}
```

Estados posibles: `OK`, `REVISAR`, `RECHAZADO`.

---

## 11. Interfaz web

Rutas (todas sin auth, la app es local):

| Método | Ruta | Qué hace |
| --- | --- | --- |
| `GET` | `/` | Pantalla principal: dropzone + estado de la config |
| `POST` | `/procesar` | Sube archivos, los analiza y **decide si hace falta preguntar** |
| `POST` | `/contrapartes/confirmar` | Recibe los nombres de las contrapartes nuevas y ejecuta el proceso |
| `GET` | `/descargar/{corrida}` | Descarga el ZIP con los archivos renombrados |
| `GET` | `/ajustes` | Formulario de RFC y plantilla |
| `POST` | `/ajustes` | Guarda RFC y plantilla |
| `GET` | `/contrapartes` | Lista de contrapartes cacheadas |
| `PATCH` | `/contrapartes/{contraparte}` | Renombra una contraparte |
| `DELETE` | `/contrapartes/{contraparte}` | Borra una contraparte del cache |

### Flujo de subida — dos fases

La web no tiene un prompt de terminal, así que el paso interactivo de §8.4 se
resuelve con un formulario intermedio:

1. **Fase A (analizar).** Se suben los archivos a un directorio temporal
   (`storage/app/corridas/{uuid}/entrada`). Se parsean **sin copiar ni renombrar
   nada** y se recolectan los RFCs de contraparte que **no** están en la tabla.
2. Si hay RFCs nuevos, se muestra un formulario con una fila por RFC: el RFC, la
   razón social del CFDI y un input **precargado con la sugerencia automática**.
   El usuario puede editar cada uno.
3. **Fase B (procesar).** Se guardan esas contrapartes y se corre el
   `ProcesadorDeCfdis` en **modo no interactivo** — para entonces ya no queda
   nada que preguntar. La salida va a
   `storage/app/corridas/{uuid}/salida`.
4. Si no había RFCs nuevos, la fase A encadena directo a la fase B.

En la web:

- Siempre se **copia**, nunca se mueve (los originales son subidas temporales).
- El resultado se muestra como una tabla HTML con las mismas cuatro columnas —
  `Estado`, `Original`, `Nuevo nombre`, `Nota` — con el estado codificado por
  color (`OK` verde, `REVISAR` ámbar, `RECHAZADO` rojo), las advertencias
  listadas debajo, y el conteo `X/Y procesados correctamente.`
- Botón **Descargar ZIP** con todos los archivos renombrados. Los rechazados
  **no** van en el ZIP.
- Validación de subida: solo `.pdf` y `.xml`, y un tope razonable de tamaño por
  archivo. Un archivo con otra extensión aparece como `RECHAZADO` en la tabla,
  no como error de validación de Laravel.

### Limpieza

Las corridas viejas en `storage/app/corridas/` se borran con un comando
`php artisan cfdi:limpiar` (por ejemplo, las de más de 24 horas). No hace falta
scheduler.

---

## 12. Comando Artisan

Debe ser un puerto 1:1 del CLI de Python.

```
php artisan cfdi:autofile [archivos...]
    {--dir=}            Procesa una carpeta completa (no recursivo)
    {--o|output=}       Carpeta de salida (default ./Renombradas)
    {--move}            Mueve en vez de copiar
    {--no-interactivo}  No pregunta nada; usa sugerencias automáticas
    {--rfc=}            RFC propio para esta corrida (no lo guarda)
    {--template=}       Plantilla para esta corrida (no la guarda)
    {--set-rfc=}        Guarda tu RFC y termina
    {--set-template=}   Guarda la plantilla default y termina
```

### Orden de ejecución

1. Si viene `--set-rfc`: guarda, imprime `RFC guardado: {RFC}`, sale con **0**.
2. Si viene `--set-template`: guarda, imprime `Plantilla guardada: {plantilla}`,
   sale con **0**.
3. Si no hay archivos **ni** `--dir`: imprime la ayuda y sale con **1**.
4. Resuelve el RFC: `--rfc` gana sobre el guardado en `ajustes`.
   - Si no hay ninguno y **sí** es interactivo, lo pregunta
     (`Tu RFC (se guarda para la proxima): `) y **lo guarda**.
   - Si no hay ninguno y **no** es interactivo:
     `Error: no hay RFC configurado. Usa --set-rfc o --rfc.` a **stderr**, sale con **1**.
   - Si el usuario no contesta nada:
     `Error: se necesita tu RFC para saber quien es la contraparte.` a stderr, sale con **1**.
5. Resuelve la plantilla: `--template` > `ajustes.plantilla` > default.
6. Resuelve la salida: `--output` > `./Renombradas` (relativa al CWD).
7. Recolecta (§10.4). Si no quedó ningún archivo procesable: imprime la tabla de
   errores si la hay, luego `No hay archivos PDF/XML que procesar.` y sale con **1**.
8. Procesa, imprime `Salida: {ruta absoluta}`, la tabla, las advertencias y el
   conteo.

### Códigos de salida

| Código | Cuándo |
| --- | --- |
| `0` | todos los archivos se procesaron correctamente |
| `2` | hubo al menos un rechazo (`okArchivos !== totalArchivos`) |
| `1` | error de uso: sin argumentos, sin RFC, o nada que procesar |

### La tabla

Se usa `$this->table()` de Laravel con las columnas `Estado`, `Original`,
`Nuevo nombre`, `Nota`. El nombre nuevo de un rechazado es `—` (guion largo).

> El Python trae un renderizador de tablas propio (`render_tabla`) que ajusta
> anchos al ancho de la terminal y parte celdas largas en varias líneas. **Eso no
> se porta**: la tabla de Symfony Console que usa Laravel ya resuelve el mismo
> problema. El test correspondiente (`test_cli_imprime_tabla_con_antes_y_despues`)
> se porta solo en la parte que verifica que aparezcan los encabezados y los
> nombres de archivo, no la geometría de los bordes.

### Advertencias

Se imprimen bajo un encabezado `Advertencias:`, una por línea, con el formato
`⚠ {archivo}: {texto}`.

---

## 13. Tests (Pest) — criterio de aceptación

Se portan los cuatro archivos de test del Python. **La suite debe pasar completa
antes de dar la migración por terminada.**

### `tests/Unit/XmlCfdiParserTest.php` (de `test_parser.py`)

- Extrae todos los campos de un CFDI 4.0 (fixture `ejemplo_cfdi.xml`): fecha
  `2026-02-14T11:32:05`, total `11600.00`, tipo `I`, emisor `AAA010101AAA` /
  `SERVICIOS CONTABLES DEL NORTE SA DE CV`, receptor `BBBB010101BBB` /
  `RECEPTOR DE PRUEBA`, uuid `A1B2C3D4-E5F6-4789-ABCD-1234567890AB`,
  `uuidSustituido` null, confianza `alta`, `esNotaCredito()` false.
- Rechaza un XML que no es CFDI (`no_es_cfdi.xml`) con `"no es un CFDI valido"`.
- Detecta nota de crédito + sustitución (`cfdi_nota_credito.xml`): tipo `E`,
  `esNotaCredito()` true, `uuidSustituido = 11111111-2222-3333-4444-555555555555`.
- **Acepta CFDI 3.3**: el mismo fixture con el namespace cambiado a `.../cfd/3`.
- Rechaza si falta `Fecha` (mensaje contiene `falta el atributo Fecha`).
- Rechaza si falta `Total` (mensaje contiene `falta el atributo Total`).
- Rechaza si falta el RFC del Emisor (mensaje contiene `RFC del Emisor`).

### `tests/Unit/PdfCfdiParserTest.php` (de `test_parser_pdf.py`)

Se inyecta un `PdfTextExtractor` falso que devuelve texto fijo — no hace falta
generar PDFs reales, a diferencia del Python.

Texto base de un CFDI válido:

```
FACTURA ELECTRONICA CFDI 4.0
Emisor: SERVICIOS CONTABLES DEL NORTE SA DE CV
RFC Emisor: AAA010101AAA
Receptor: RECEPTOR DE PRUEBA   RFC: BBBB010101BBB
Fecha de emision: 2026-04-05T10:15:00
Folio Fiscal (UUID): 7F8E9D0C-1B2A-4C3D-8E5F-0A1B2C3D4E5F
Subtotal: $2,500.00
IVA 16%: $400.00
Total: $2,900.00
```

- Acepta con confianza `baja`, fuente `pdf`, fecha `2026-04-05`,
  **total `2900.00` (no debe confundir el Subtotal con el Total)**, uuid en
  minúsculas `7f8e9d0c-1b2a-4c3d-8e5f-0a1b2c3d4e5f`, los dos RFCs presentes,
  `tipoComprobante === null` (no se inventa), y al menos una advertencia.
- Rechaza sin texto extraíble → `no tiene texto extraible`.
- Rechaza con menos de dos RFCs → `al menos 2`.
- Rechaza sin UUID ni "folio fiscal" → `no parece estar timbrado`.
- Rechaza sin total → `no se pudo identificar un total`.
- **Acepta con la frase "Folio fiscal: pendiente" sin UUID**: `uuid === null` y
  total `150.00`.

### `tests/Unit/NamingTest.php` (de `test_naming.py`)

- `sugerir` quita forma societaria y limita a 3 palabras (los 3 casos de §8).
- RFC genérico (`XAXX010101000`, `XEXX010101000`) → `General`.
- No interactivo usa la sugerencia **y la persiste** en `contrapartes`.
- Si el RFC ya está cacheado, **no pregunta** aunque sea interactivo.
- Interactivo guarda la respuesta del usuario.
- `construirNombre` con la plantilla default → `2026-02-14 G Contador - 11600.00.pdf`.
- Nota de crédito + sanitizado → `2026-03-01 G Cafe Pinon - 1160.00 (nota de credito).xml`,
  y el resultado no contiene `,`, `$` ni `ñ`.
- Plantilla personalizada con `{uuid}`.

### `tests/Feature/CfdiAutofileCommandTest.php` (de `test_cli.py`)

Cada test corre contra una base SQLite en memoria y un directorio temporal.

- Copia un CFDI válido, deja el original intacto, código **0**, imprime
  `1/1 procesados correctamente.`, y crea
  `2026-02-14 G SERVICIOS CONTABLES NORTE - 11600.00.xml`.
- Rechaza un XML que no es CFDI: código **2**, imprime `RECHAZADO` y
  `no es un CFDI valido`, `0/1 procesados correctamente.`, la carpeta de salida
  queda **vacía** y el original sigue ahí.
- `--move` saca el original de la carpeta de entrada.
- Dos copias del mismo CFDI producen
  `… - 11600.00.xml` y `… - 11600.00 (2).xml`.
- `--set-rfc bbbb010101bbb` guarda `BBBB010101BBB` (en mayúsculas) y
  `--set-template` persiste la plantilla.
- Sin `--output`, la salida cae en `./Renombradas`.
- La tabla del resumen incluye los cuatro encabezados y los nombres de ambos
  archivos.

### `tests/Feature/` — nuevos, propios de la web

Estos no existen en el Python. Son el mínimo para dar la web por buena:

- `POST /procesar` con un CFDI cuya contraparte **ya está cacheada** procesa
  directo y devuelve la tabla de resultados.
- `POST /procesar` con una contraparte **nueva** devuelve el formulario
  intermedio con la sugerencia precargada, y **todavía no** ha escrito nada en
  la carpeta de salida.
- `POST /contrapartes/confirmar` guarda el nombre editado por el usuario y
  produce el archivo con ese nombre.
- Un archivo rechazado **no** aparece en el ZIP de descarga.
- `POST /ajustes` guarda el RFC en mayúsculas.

### Fixtures

Se copian tal cual desde `tests/fixtures/` del Python:
`ejemplo_cfdi.xml`, `cfdi_nota_credito.xml`, `no_es_cfdi.xml`.

---

## 14. Orden de implementación sugerido

1. `laravel new` con Herd, SQLite, Pest. Copiar los fixtures.
2. Migraciones + modelos `Ajuste` y `Contraparte` + helper `Ajustes`.
3. `CfdiData`, `CfdiRechazadoException`, `XmlCfdiParser` + su test. **Verde.**
4. `PdfTextExtractor` (interfaz + fake + smalot), `PdfCfdiParser` + su test. **Verde.**
5. `Sanitizador`, `SugeridorDeNombre`, `ResolvedorDeContraparte`,
   `ConstructorDeNombre` + `NamingTest`. **Verde.**
6. `AgrupadorDeArchivos` + `ProcesadorDeCfdis`.
7. `CfdiAutofileCommand` + su test de feature. **Verde.**
8. Web: ajustes → subida en dos fases → tabla → ZIP → contrapartes.
9. `php artisan cfdi:limpiar`.

Los pasos 3 a 7 son paridad pura con el Python y se pueden verificar contra él
corriendo los dos lado a lado sobre los mismos archivos.

---

## 15. Fuera de alcance

No implementar, ni siquiera "por si acaso":

- Login, roles, multiusuario.
- Validación del sello digital o consulta al webservice del SAT.
- OCR para PDFs escaneados.
- Watcher de carpetas, colas, jobs en background, scheduler.
- Historial persistente de facturas procesadas.
- Integración con Google Drive, Sheets, Dropbox o contabilidad.
- API pública.
