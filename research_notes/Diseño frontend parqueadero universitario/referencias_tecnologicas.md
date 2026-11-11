# Referencias tecnológicas / SaaS / movilidad para el frontend de UniPark (estilos industrial, neo-brutalista, suizo, tipográfico, artesanal)

Fecha de verificación: 2026-11-08 / 2026-11-09. Todos los sitios principales se descargaron con `curl` (HTTP 200) y su CSS enlazado se inspeccionó buscando `font-family`, `@font-face`, archivos `.woff2`, colores hex, `border-radius` y `box-shadow`. Las tipografías indicadas salen **solo** del CSS/@font-face del propio sitio (evidencia = la URL del sitio). Los hex salen del CSS (variables o frecuencia de aparición). No se tomaron capturas de pantalla: las observaciones visuales que no salen del CSS se citan a fuentes externas o se marcan como inferencia.

Metodología de evidencia: `curl -L https://<sitio>` + descarga de las primeras hojas `.css` enlazadas + `grep` de `font-family`, `@font-face`, `#xxxxxx`, `border-radius`, `box-shadow`.

## ¿Qué sitios reales sirven como referencia (URL del sitio + URL de galería, verificados hoy)?

### Takeaway
Hay 10 candidatos sólidos y verificados (HTTP 200): Teenage Engineering, Nothing, SBB Digital (sistema de diseño de los ferrocarriles suizos), Uber Base, Gumroad, PostHog, HEY (37signals), Are.na, Stripe Press y Mercury. Solo algunos tienen una ficha de galería confirmada (landing.love, Lapa Ninja, SaaS Landing Page); para SBB, Uber Base, HEY, Are.na y Nothing no se encontró una ficha de galería que apunte al dominio correcto, así que hay que tratarlos como "referencia verificada en origen" y no "encontrada en galería".

### Cited Findings

**Tabla de verificación (sitio → galería)**

| # | Sitio (HTTP 200) | Galería verificada | Estilo principal |
|---|---|---|---|
| 1 | https://teenage.engineering | https://www.lapa.ninja/tag/teenageengineering (aparece en búsqueda como "3 teenage engineering website designs"; WebFetch devolvió 403, no abierto) | Industrial / técnico |
| 2 | https://nothing.tech | Sin galería confirmada (la ficha https://www.awwwards.com/sites/nothing corresponde a nothing.dk, otro sitio) | Industrial / técnico (dot-matrix) |
| 3 | https://digital.sbb.ch | Sin galería; es la documentación oficial del sistema de diseño | Suizo internacional / movilidad |
| 4 | https://base.uber.com | Sin galería; documentación oficial del sistema de diseño | Suizo / tipográfico / movilidad |
| 5 | https://gumroad.com | https://www.lapa.ninja/post/gumroad/ (200, "Gumroad - Landing Page Design \| Lapa Ninja"); https://saaslandingpage.com/gumroad/ (200, sin título legible) | Neo-brutalismo |
| 6 | https://posthog.com | https://www.landing.love/sites/posthog (200); https://saaslandingpage.com/posthog/ (200, "PostHog page - SaaS Landing Page") | Artesanal / retro-OS |
| 7 | https://hey.com | Sin galería confirmada (la ficha https://www.awwwards.com/sites/hey apunta a hey.qed42.com, NO a hey.com) | Artesanal / tipográfico |
| 8 | https://www.are.na | Sin galería confirmada (minimal.gallery/are-na/ → 404; brutalistwebsites.com/are.na/ → 404) | Minimalismo tipográfico |
| 9 | https://press.stripe.com | https://www.landing.love/sites/stripe-press (200, "Stripe Press Website Design — landing.love") | Minimalismo tipográfico editorial (fintech) |
| 10 | https://mercury.com | https://saaslandingpage.com/mercury/ (200, "Mercury page - SaaS Landing Page") | Fintech editorial (con reservas) |

- Fichas descartadas por no corresponder al dominio: la página Awwwards "Nothing - Awwwards Honorable Mention" enlaza a `http://www.nothing.dk`; "Hey! - Awwwards Nominee" enlaza a `https://hey.qed42.com` — [Awwwards Nothing](https://www.awwwards.com/sites/nothing); [Awwwards Hey](https://www.awwwards.com/sites/hey)
- Godly (`godly.website/website/<slug>`) responde 200 pero con título genérico "Recent — Design Inspiration" y sin el nombre del sitio en el HTML (SPA) → no se pudo confirmar ninguna ficha en Godly. SiteInspire devolvió 429 (Vercel Security Checkpoint) → no verificable.
- Ramp: https://www.landing.love/sites/ramp existe pero corresponde a "ramp.design", no a ramp.com — [landing.love](https://www.landing.love/sites/ramp)

### Inferences
- Para el informe final conviene presentar SBB Digital y Uber Base como "sistemas de diseño de movilidad" (fuente primaria) en lugar de fichas de galería; son las más pertinentes para un sistema de parqueadero aunque no estén en galerías.

### Gaps
- No se pudo confirmar presencia en Godly, SiteInspire, Httpster, Brutalist Websites ni Fonts In Use para ninguno de los 10 (SPA, bloqueo antibots o 404).
- Bloomberg Terminal (bloomberg.com/professional/...) devolvió 403 → excluido. TfL, Bending Spoons, Field.io, Raycast-design, Read.cv, Cron no se verificaron por límite de tiempo.

## ¿Qué tipografías usa cada sitio (solo con evidencia)?

### Takeaway
Todas las tipografías siguientes se extrajeron del CSS/@font-face servido por cada sitio el 2026-11-08. Patrón claro: los sitios con identidad fuerte usan tipografías propias o de fundición (Univers personalizada, SBB Web, Uber Move, ABC Favorit, Moniker, Ivar, Arcadia/Tiempos), casi siempre emparejadas con una monoespaciada para datos.

### Cited Findings
- **Teenage Engineering**: `@font-face` para `te-20`, `te-40`, `UniversTE20T`, `UniversTE40L`, `TechnoType`, `Unicode`, además de display `franxurter`, `riddim`, `riddimscript`, `swingus`; variables `--te-20:"te-20","Unicode",sans-serif` y `--te-40` — [CSS de teenage.engineering](https://teenage.engineering). (Inferencia: los nombres "UniversTE20T/40L" indican una versión propia de Univers; no hay documento de marca que lo confirme.)
- **Nothing**: `Ndot-Regular`, `Ndot-Bold` (dot-matrix), `NType82-Regular`, `LatteraMonoLL`, `Geist Variable`, `Geist Mono Variable` — [CSS de nothing.tech](https://nothing.tech)
- **SBB Digital**: `font-family: SBB, Inter, …` y archivos `SBBWeb-Light.woff2`, `SBBWeb-Roman.woff2`, `SBBWeb-Bold.woff2`; variable `--sbb-typo-font-family` — [CSS de digital.sbb.ch](https://digital.sbb.ch)
- **Uber Base**: archivos `UberMove-Bold.woff2`, `UberMoveText-Regular.woff2`, `UberMoveText-Bold.woff2`; la interfaz del portal de documentación usa `DM sans` — [CSS de base.uber.com](https://base.uber.com)
- **Gumroad**: `ABC Favorit` (Regular, Bold, Italic; `ABCFavorit-*.woff2`), fallback `Avenir, Montserrat, Corbel, URW Gothic…` — [CSS de gumroad.com](https://gumroad.com)
- **PostHog**: `IBM Plex Sans Variable`, `Source Code Pro`, `RoundHog` (Medium/SemiBold/Bold + itálicas; tipografía propia), además se sirven `BricolageGrotesque`, `DMSans`, `Fraunces` (ttf) — [CSS de posthog.com](https://posthog.com)
- **HEY (37signals)**: `Moniker`, `Really Sans Large`, `Shantell Sans`, `Martian Mono` (`MonikerWebBold.woff2`, `MartianMono-StdLt.woff2`…) — [CSS de hey.com](https://hey.com). Moniker es una sans redondeada de Eric Olson (Process Type Foundry, 2017) — [Process Type Foundry](https://processtypefoundry.com/fonts/moniker)
- **Are.na**: `areal, areal Fallback, Arial, Helvetica, sans-serif` mediante `--fonts-sans`, más `--fonts-mono` — [CSS de are.na](https://www.are.na)
- **Stripe Press**: `Ivar Headline`, `Ivar Text`, `Ivar Display` (fallback Georgia) — [CSS de press.stripe.com](https://press.stripe.com)
- **Mercury**: `--font-arcadia`, `--font-arcadia-display`, `--font-tiempos`, `--font-tiempos-fine`, `--font-tiempos-headline`, `IBM Plex Mono` — [CSS de mercury.com](https://mercury.com)
- Complementarios (no en el top 10): **Ramp** `Lausanne` + `IBM Plex Sans`/`IBM Plex Mono` — [ramp.com](https://ramp.com); **Raycast** `Inter`, `Geist Mono`, `Instrument Serif` — [raycast.com](https://www.raycast.com); **Linear** solo `InterVariable.woff2` — [linear.app](https://linear.app)

### Inferences
- Para UniPark: una sans grotesca/neogrotesca + una monoespaciada para placas, horas y montos aparece en casi todas las referencias (Nothing: Geist + Geist Mono; PostHog: Plex + Source Code Pro; Mercury: Arcadia + Plex Mono; HEY: Moniker + Martian Mono).
- Linear usa solo Inter → confirma que es la opción "genérica" que el encargo quiere evitar.

### Gaps
- No se encontró entrada de Fonts In Use para ninguno de estos sitios; la búsqueda solo devolvió el Rabbit R1 (relacionado con Teenage Engineering) — [Fonts In Use](https://fontsinuse.com/designers/23610/rabbit-inc-1).
- Que "areal" (Are.na) sea una Arial modificada/propia es inferencia por el nombre; no verificado.

## Jerarquía, paleta, grilla, botones, bordes vs. sombras y detalles "hechos a mano" de cada sitio

### Takeaway
Las referencias más útiles para un panel de parqueadero comparten: radios mínimos o nulos, bordes de 1px en lugar de sombras difusas, paletas casi monocromas con un solo acento fuerte, y tipografía monoespaciada para datos. PostHog y HEY aportan la capa "artesanal"; SBB/Uber aportan el rigor de movilidad.

### Cited Findings

**1. Teenage Engineering — industrial/técnico**
- Paleta del CSS: gris claro `#f1f2f2` (46 apariciones, el fondo dominante), `#e5e5e5`, `#000000`, y acentos puntuales `#fab413` (amarillo), `#f05a24` (naranja), `#ed2024` (rojo) — [CSS teenage.engineering](https://teenage.engineering)
- `border-radius:0` es el valor más frecuente; `box-shadow:none` — [CSS teenage.engineering](https://teenage.engineering)
- Navegación textual en minúsculas ("product", "instruments", "store", "now", "guides"), carrito mostrado como "checkout0", botones con copy no convencional ("agree!", "life is fantastic!") — [teenage.engineering](https://teenage.engineering)
- Para UniPark: fondo gris técnico, acento naranja/amarillo tipo "señal", etiquetas en minúscula y contadores pegados al texto ("salidas0") → aspecto de instrumento.

**2. Nothing — industrial (dot-matrix)**
- Tipos `Ndot` (matriz de puntos) para titulares + `Geist`/`Geist Mono`/`LatteraMonoLL` — [CSS nothing.tech](https://nothing.tech)
- Grises `#b1b3b3`, `#f4f4f4`; acentos `#ffee00`, `#ff42ad`, `#27d4e0` presentes en CSS — [CSS nothing.tech](https://nothing.tech)
- Radios: hay `999px` (píldoras) y `.5rem` → no es 100% recto (CSS).
- Para UniPark: la tipografía dot-matrix es ideal para un display de "cupos disponibles" o el contador de placa, evocando paneles LED de parqueaderos reales.

**3. SBB Digital — suizo internacional / movilidad**
- Tokens de color del CSS: `--sbb-color-red: #eb0000`, `--sbb-color-red125: #c60018`, `--sbb-color-red150: #a20013`, `--sbb-color-red85: #ff3838`, `--sbb-color-black: #000000`, `--sbb-color-charcoal: #212121`, `--sbb-color-iron: #444444`, `--sbb-color-cloud: #e5e5e5`, `--sbb-color-milk: #f6f6f6`, `--sbb-color-white: #ffffff` — [CSS digital.sbb.ch](https://digital.sbb.ch)
- Escala de radios en múltiplos de 0,125rem: `--sbb-border-radius-1x: 0.125rem`, `2x: 0.25rem`, `4x: 0.5rem`, `16x: 2rem` — [CSS digital.sbb.ch](https://digital.sbb.ch)
- Usa sombras con nombre (`--sbb-box-shadow-level-9-hard`, `--sbb-button-box-shadow`) → sombras sistematizadas, no decorativas (CSS).
- Tipografía propia SBB Web en 3 pesos (Light/Roman/Bold) — [CSS digital.sbb.ch](https://digital.sbb.ch)
- Para UniPark: modelo de tokens (nombres de color tipo "milk/cloud/iron/charcoal"), un rojo único de marca para alertas/acciones, y escala de radios pequeña y sistemática.

**4. Uber Base — suizo/tipográfico, movilidad**
- Familia propia Uber Move / Uber Move Text (Regular, Bold) servida en el portal — [CSS base.uber.com](https://base.uber.com)
- Para UniPark: separar una tipografía "display" (Move) de una de "texto" (Move Text) para tablas densas de entradas/salidas.

**5. Gumroad — neo-brutalismo**
- Negro `#000000` domina (103 apariciones); acento rosa `#ff90e8` (`--accent: 255 144 232`); gris cálido `--color-gray: #f4f4f0`; `--color-dark-gray: #242423`; `--color-green: #23a094` — [CSS gumroad.com](https://gumroad.com)
- Bordes sólidos `border: solid .0625rem` (1px) con color derivado; radios `.25rem`, `0` y píldora — [CSS gumroad.com](https://gumroad.com)
- Descripción externa (secundaria, ingeniería inversa): rosa fuerte sobre crema, ABC Favorit a 96px, bordes negros duros y sombras desplazadas — [webdesignhot](https://www.webdesignhot.com/design.md/gumroad/); el rediseño de 2021 se cita como punto de inflexión del neo-brutalismo — [setproduct](https://www.setproduct.com/blog/retro-brutalist-ui-design-2026)
- Hero real: "Go from 0 to $1", chips de categorías con icono, cifra grande de ingresos — [gumroad.com](https://gumroad.com)
- Para UniPark: cifras gigantes (recaudo del día), chips de filtro con borde negro, un único color de acento saturado para "Pagar".

**6. PostHog — artesanal / interfaz tipo sistema operativo**
- Paleta CSS: naranja `#f54e00` (35), crema `#f5e2b2` (34), casi negro `#1e1f23`, `#161616`, `#eb9d2a`, `#53ffcb` — [CSS posthog.com](https://posthog.com)
- Radios: `3px` (132 apariciones) y `0` (82) → esquinas casi rectas — [CSS posthog.com](https://posthog.com)
- El sitio se diseñó como un sistema operativo: ventanas movibles con "snapping", un clon de Explorador de Windows como página de productos, el changelog como hoja de cálculo, foros tipo Outlook Express; publicado el 10-sep-2025 por Cory Watilo — [PostHog blog "Why our website looks like an operating system"](https://posthog.com/blog/why-os)
- El equipo pasa de wireframes Balsamiq directo a código, saltando a menudo el diseño hi-fi — [PostHog Handbook](https://posthog.com/handbook/brand/designing-posthog-website)
- Tipografía propia `RoundHog` — [CSS posthog.com](https://posthog.com)
- Para UniPark: historial de transacciones como "hoja de cálculo" real (celdas con borde, cabecera fija), ventanas/paneles con barra de título para el panel de admin; ilustraciones propias en vez de iconos stock.

**7. HEY (37signals) — artesanal/tipográfico**
- Mezcla tipográfica deliberada: `Moniker` (redondeada), `Really Sans Large`, `Shantell Sans` (aspecto manuscrito), `Martian Mono` — [CSS hey.com](https://hey.com)
- Colores en CSS: `#5522fa` (violeta), `#17212b`, `#0074e4`; un solo `border-radius: 0.25em`; `box-shadow: none` — [CSS hey.com](https://hey.com)
- Descrito externamente como "email opinionado vestido de póster amistoso" — [webdesignhot](https://www.webdesignhot.com/design.md/hey-com/)
- Para UniPark: una tipografía manuscrita (tipo Shantell) solo para notas/anotaciones humanas (p. ej., observaciones del vigilante), nunca para datos.

**8. Are.na — minimalismo tipográfico**
- Una sola familia sans (`areal` → Arial/Helvetica) + mono; colores por tokens `--colors-*` (p. ej. `--colors-blue2:#5E6DEE`, `--colors-alert:#FF7A30`) con variantes claro/oscuro y `--colors-blend: multiply/screen` — [CSS are.na](https://www.are.na)
- Sombra mínima: `box-shadow:0 0 0 2px var(--colors-focus)` para foco — [CSS are.na](https://www.are.na)
- Para UniPark: tipografía de sistema bien jerarquizada, color solo semántico (alerta, foco), anillo de foco visible para accesibilidad.

**9. Stripe Press — minimalismo tipográfico editorial (fintech)**
- Serif Ivar (Headline/Text/Display) como única familia — [CSS press.stripe.com](https://press.stripe.com)
- Amarillo `#ffd943`/`#ffd942`, `#222222`, `#cad9e5`, `#6e665b`, `#0d121f` — [CSS press.stripe.com](https://press.stripe.com)
- Para UniPark: inspiración para recibos/comprobantes de pago imprimibles (serif + amarillo papel), no para el dashboard.

**10. Mercury — fintech (con reservas)**
- Arcadia (sans) + Tiempos (serif) + IBM Plex Mono; color dominante `#272735` (427 apariciones), `#535461`, `#fbfcfd` — [CSS mercury.com](https://mercury.com)
- Usa `--radius-xl` y `--radius-5xl` → más redondeado que el resto (CSS).
- Para UniPark: emparejar serif editorial para totales/titulares con mono para montos; evitar sus radios grandes.

### Inferences
- Bordes vs. sombras: TE, HEY y Gumroad tienen `box-shadow:none` como valor explícito y radios 0–4px; PostHog 3px/0. Es la línea a seguir para evitar el "look de plantilla IA" (tarjetas redondeadas con sombra difusa). Mercury, Ramp y Nothing tienen más radio → usar solo como referencia tipográfica.
- Combinación propuesta para UniPark: rigor de tokens SBB (rojo único + grises con nombre) + display dot-matrix tipo Nothing para cupos/placas + tablas tipo hoja de cálculo de PostHog + acento y cifras grandes de Gumroad para pagos.

### Gaps
- No se analizaron capturas: grilla exacta (columnas/gutter), iconografía y estilo de visualización de datos de cada sitio no se verificaron visualmente; deben confirmarse abriendo los sitios.
- Los conteos de hex reflejan solo el HTML + primeras 6 hojas CSS enlazadas; pueden faltar colores cargados por JS.
- No se encontró documentación pública de tipografía/color de SBB (la URL https://digital.sbb.ch/en/foundation/typography/ dio 404; el WebFetch del home dio 418) ni del portal Uber Base (contenido renderizado por JS).
