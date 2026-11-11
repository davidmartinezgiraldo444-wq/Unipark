# Tipografía y color para UniPark (parqueadero universitario, UI en español)

> Método general de estas notas
> - **Datos de catálogo** (licencia, pesos, variable, subsets) tomados de la API pública de Fontsource (`api.fontsource.org/v1/fonts`), que replica el catálogo de Google Fonts.
> - **Verificación propia de archivos**: se descargaron los WOFF `latin-400-normal` de cada familia desde `cdn.jsdelivr.net/npm/@fontsource/...` y con un script Node se leyeron las tablas `cmap`, `GSUB`, `hmtx`. Se comprobó la presencia de `á é í ó ú Á É Í Ó Ú ñ Ñ ü Ü ¿ ¡ €`, la lista de funciones OpenType y el ancho de avance de los dígitos 0–9 (si los 10 anchos son iguales, las cifras son tabulares por defecto). Script en el scratchpad de la sesión (no forma parte del proyecto).
> - **Contraste**: fórmula WCAG 2.x (luminancia relativa con linealización sRGB, umbral 0.04045; ratio = (L1+0.05)/(L2+0.05)), calculada en Node, sin redondear antes de comparar — [W3C, Understanding 1.4.3](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html).
> - **Daltonismo**: simulación Machado et al. 2009 (severidad 1.0) aplicada en RGB lineal, y diferencia de color ΔE76 en CIELAB entre éxito / aviso / peligro. Es una aproximación (ΔE76 < ~10 = prácticamente indistinguibles; > ~20 = separables). No sustituye pruebas con usuarios.

---

## 1. Tipografías candidatas: ficha técnica, licencia, soporte español y cifras tabulares

### Takeaway
Las 30 familias libres evaluadas (todas OFL‑1.1 vía Google Fonts/Fontsource) cubren el español completo (á é í ó ú ñ ü ¿ ¡). La diferencia real está en las **cifras tabulares**: Space Grotesk, Public Sans, Archivo, Barlow, Overpass, Familjen, Schibsted, Instrument Sans, Bricolage, Atkinson Next, Source Serif 4 y Newsreader exponen `tnum`; IBM Plex Sans, Hanken Grotesk, Familjen, Newsreader y Source Serif 4 ya traen cifras tabulares *por defecto*; Fraunces e Instrument Serif **no tienen `tnum`** (solo para titulares). Además, Google Fonts elimina funciones de las versiones que sirve (p. ej. `zero`, `ss01–ss06`, `onum` de IBM Plex), así que para funciones avanzadas conviene autoalojar los archivos del repositorio original.

### Cited Findings

**Tabla A — Datos de catálogo (Fontsource API) + verificación de archivos (script propio)**

| Familia | Categoría | Pesos | Itálica | Variable | Licencia | Español (latin) | `tnum` en archivo GF | Cifras por defecto |
|---|---|---|---|---|---|---|---|---|
| IBM Plex Sans | sans | 100–700 | sí | sí | OFL‑1.1 | completo | no (solo `frac`, `liga`) | **tabulares** (todas 600 u) |
| IBM Plex Mono | mono | 100–700 | sí | no | OFL‑1.1 | completo | n/a (mono) | tabulares |
| IBM Plex Serif | serif | — | sí | — | OFL‑1.1 | completo | no | (no medido) |
| Space Grotesk | sans | 300–700 | **no** | sí | OFL‑1.1 | completo | **sí** (`tnum`,`pnum`) | proporcionales |
| Space Mono | mono | 400, 700 | sí | no | OFL‑1.1 | completo | n/a | tabulares |
| JetBrains Mono | mono | 100–800 | sí | sí | OFL‑1.1 | completo | n/a (`calt` ligaduras de código) | tabulares |
| Atkinson Hyperlegible Next | sans | 200–800 | sí | sí | OFL‑1.1 | completo | **sí** | proporcionales |
| Atkinson Hyperlegible Mono | mono | 200–800 | sí | sí | OFL‑1.1 | completo | n/a | tabulares |
| Public Sans | sans | 100–900 | sí | sí | OFL‑1.1 | completo | **sí** | proporcionales |
| Archivo | sans | 100–900 | sí | sí (incl. ancho) | OFL‑1.1 | completo | **sí** | casi tabulares (521–574 u) |
| Archivo Narrow | sans | 400–700 | sí | sí | OFL‑1.1 | completo | **sí** | — |
| Barlow | sans | 100–900 | sí | **no** (estáticas) | OFL‑1.1 | completo | **sí** | proporcionales |
| Barlow Condensed | sans | 100–900 | sí | no | OFL‑1.1 | completo | **sí** | — |
| Overpass | sans | 100–900 | sí | sí | OFL‑1.1 | completo | **sí** | proporcionales |
| Overpass Mono | mono | — | — | — | OFL‑1.1 | completo | `tnum` presente | tabulares |
| B612 / B612 Mono | sans/mono | 400, 700 | sí | no | OFL‑1.1 | completo (sin latin‑ext) | **sin GSUB** | tabulares (1300 u) |
| Instrument Sans | sans | 400–700 | sí | sí | OFL‑1.1 | completo | **sí** | proporcionales |
| Instrument Serif | serif | **solo 400** | sí | no | OFL‑1.1 | completo | **no** | proporcionales |
| Fraunces | serif | 100–900 | sí | sí | OFL‑1.1 | completo | **no** | proporcionales |
| Newsreader | serif | 200–800 | sí | sí | OFL‑1.1 | completo | **sí** | **tabulares** |
| Source Serif 4 | serif | 200–900 | sí | sí | OFL‑1.1 | completo | **sí** | **tabulares** |
| Familjen Grotesk | sans | 400–700 | sí | sí | OFL‑1.1 | completo | **sí** | **tabulares** (680 u) |
| Schibsted Grotesk | sans | 400–900 | sí | sí | OFL‑1.1 | completo | **sí** | proporcionales |
| Hanken Grotesk | sans | 100–900 | sí | sí | OFL‑1.1 | completo | no | **tabulares** (560 u) |
| Bricolage Grotesque | sans | 200–800 | **no** | sí (incl. óptico/ancho) | OFL‑1.1 | completo | **sí** | proporcionales |
| Commit Mono | mono | 200–700 | sí | no | OFL‑1.1 | completo (sin latin‑ext en subset) | n/a; 11 `cv`, 5 `ss` | tabulares |
| Geist | sans | 100–900 | sí | sí | OFL‑1.1 | completo | **sí** | proporcionales |
| Geist Mono | mono | — | — | — | OFL‑1.1 | completo | n/a | tabulares |
| Inter | sans | 100–900 | sí | sí | OFL‑1.1 | completo | **sí** (515 glifos en subset latin, el mayor) | proporcionales |
| Red Hat Mono | mono | — | — | — | OFL‑1.1 | completo | n/a | tabulares |

Fuentes de la tabla: [Fontsource API (catálogo)](https://api.fontsource.org/v1/fonts) — pesos, estilos, variable, licencia, subsets; columnas `tnum`/cifras/español: verificación propia de los WOFF publicados en [jsDelivr/@fontsource](https://cdn.jsdelivr.net/npm/@fontsource/inter/files/).

**Fichas individuales**

- **IBM Plex** (Sans, Serif, Mono, Sans Condensed + variantes Arabic, Devanagari, Hebrew, JP, KR, SC/TC, Thai y Math); licencia OFL 1.1; desarrollo de dos años — [IBM/plex en GitHub](https://github.com/IBM/plex). El repositorio no nombra al diseñador en la portada (la atribución habitual a Mike Abbink + Bold Monday no se verificó en esta sesión).
  - El archivo **original** de IBM (`@ibm/plex-sans@1.1.0`) trae `aalt, lnum, onum, ordn, salt, sinf, ss01–ss06, subs, sups, zero`; la versión servida por Google Fonts solo conserva `ccmp, dnom, frac, liga, numr` — verificación propia de [@ibm/plex-sans en jsDelivr](https://cdn.jsdelivr.net/npm/@ibm/plex-sans@1.1.0/) vs [@fontsource/ibm-plex-sans](https://cdn.jsdelivr.net/npm/@fontsource/ibm-plex-sans/). Sus cifras ya son tabulares por defecto (600 u).
- **Atkinson Hyperlegible Next** (2025): 7 pesos ExtraLight–ExtraBold con itálicas, variable, 370 glifos por estilo, acentos para más de 150 idiomas; **Atkinson Hyperlegible Mono**: variable Light–ExtraBold, 373 glifos; diseñadas para distinguir caracteres ambiguos (formas inequívocas, contraformas abiertas) — [Braille Institute](https://www.brailleinstitute.org/freefont/). La página del Braille Institute habla de un EULA gratuito para uso personal y comercial, mientras que el paquete de Google Fonts/Fontsource figura como OFL‑1.1 — [Fontsource API](https://api.fontsource.org/v1/fonts?id=atkinson-hyperlegible-next). (Discrepancia anotada; para el proyecto basta la distribución OFL de Google Fonts.)
- **Overpass**: sans libre basada en *Highway Gothic* (alfabetos estándar de la FHWA para dispositivos de control de tránsito), encargada por Red Hat como sustituto libre de Interstate; diseñador principal Delve Withrington (con Dave Bailey, Thomas Jockin y otros); licencia dual SIL OFL + LGPL; tiene compañera Overpass Mono — [Wikipedia: Overpass (typeface)](https://en.wikipedia.org/wiki/Overpass_(typeface)), [Fontsource: Overpass](https://fontsource.org/fonts/overpass/about).
- **Barlow**: grotesca de bajo contraste, ligeramente redondeada, de Jeremy Tribby; el repositorio dice que refleja cualidades de DIN, Interstate, Highway Gothic y la placa de California; superfamilia con anchos normal, Semi Condensed y Condensed; OFL — [jpt/barlow en GitHub](https://github.com/jpt/barlow), [Google Fonts: Barlow](https://fonts.google.com/specimen/Barlow/about). Fechas de publicación discrepan entre fuentes secundarias (2016–2017) — [Figma Fonts](https://www.figma.com/fonts/barlow/).
- **Departure Mono**: mono pixelada de Helena Zhang, fuente bajo SIL OFL (el sitio web bajo MIT, con Tobias Fried); v1.500 lista 1.186 glifos con latín, cirílico, griego básico y versalitas — [rektdeckard/departure-mono (release v1.500)](https://newreleases.io/project/github/rektdeckard/departure-mono/release/v1.500). No está en Fontsource con ese id (HTTP 404 en mi descarga), se autoaloja desde GitHub.
- **Geist**: tipografía de Vercel; desde `create-next-app` (Next.js 15) Geist sustituyó a Inter como fuente por defecto de la plantilla; se distribuye en npm `geist` y en `next/font/google` (Geist, Geist Mono, Geist Pixel) — [PR create-next-app #66737](https://git.devminer.xyz/archive/rsnext/commit/fbcc21ab00c61b64150ea0d7c384c92d2dea2ae3), [vercel.com/font](https://vercel.com/font/geist). Licencia OFL‑1.1 — [Fontsource API](https://api.fontsource.org/v1/fonts?id=geist).
- **Inter**: guías del sector la describen como “opción segura por defecto” para web apps/SaaS, diseñada para interfaces, usada por OpenAI, Notion, Linear — [Font Alternatives: startup](https://fontalternatives.com/best-fonts-for/startup/), [DesignGurus](https://designgurus.io/answers/detail/what-font-does-openai-use) (fuentes de baja autoridad; opinión, no estudio).
- **B612**: el subset de Google Fonts no incluye latin‑ext ni tabla GSUB (sin `tnum`, sin ligaduras), solo 217 glifos en latin — verificación propia + [Fontsource API](https://api.fontsource.org/v1/fonts?id=b612). Cifras monoespaciadas por defecto.
- **Commit Mono**: el subset de Fontsource trae 11 variantes de carácter (`cv01–cv11`) y 5 conjuntos estilísticos (`ss01–ss05`) — verificación propia.

**Atribuciones de diseñador/fundición (según fichas de Google Fonts; no re‑leídas en esta sesión, verificar en cada enlace):**
Space Grotesk (Florian Karsten, derivada de Space Mono de Colophon) — [GF](https://fonts.google.com/specimen/Space+Grotesk/about); JetBrains Mono (JetBrains, Philipp Nurullin) — [GF](https://fonts.google.com/specimen/JetBrains+Mono/about); Public Sans (USWDS, gobierno de EE. UU.) — [GF](https://fonts.google.com/specimen/Public+Sans/about); Archivo (Omnibus‑Type) — [GF](https://fonts.google.com/specimen/Archivo/about); B612 (Intactile/Airbus, cabinas) — [GF](https://fonts.google.com/specimen/B612/about); Instrument Sans/Serif (Rodrigo Fuenzalida / Instrument) — [GF](https://fonts.google.com/specimen/Instrument+Sans/about); Fraunces (Undercase Type) — [GF](https://fonts.google.com/specimen/Fraunces/about); Newsreader (Production Type) — [GF](https://fonts.google.com/specimen/Newsreader/about); Source Serif 4 (Frank Grießhammer, Adobe) — [GF](https://fonts.google.com/specimen/Source+Serif+4/about); Familjen Grotesk (Familjen STHLM) — [GF](https://fonts.google.com/specimen/Familjen+Grotesk/about); Schibsted Grotesk (Bakken & Bæck para Schibsted) — [GF](https://fonts.google.com/specimen/Schibsted+Grotesk/about); Hanken Grotesk (Hanken Design Co.) — [GF](https://fonts.google.com/specimen/Hanken+Grotesk/about); Bricolage Grotesque (Mathieu Triay) — [GF](https://fonts.google.com/specimen/Bricolage+Grotesque/about); Commit Mono (Eigil Nikolajsen) — [Fontsource](https://fontsource.org/fonts/commit-mono/about); Inter (Rasmus Andersson) — [GF](https://fonts.google.com/specimen/Inter/about).

**Referencias comerciales (de pago — solo como referencia de dirección de arte, no para el proyecto estudiantil):**
| Familia | Fundición | Por qué se cita | Alternativa libre cercana |
|---|---|---|---|
| Söhne | Klim Type Foundry — [klim.co.nz](https://klim.co.nz/retail-fonts/soehne/) | Akzidenz reinterpretada; estética “Swiss” contemporánea | Schibsted Grotesk, Hanken Grotesk |
| Neue Haas Grotesk | Commercial Type / Font Bureau — [commercialtype.com](https://commercialtype.com/catalog/neue_haas_grotesk) | Helvetica “original” revivida | Public Sans, Instrument Sans |
| Akzidenz‑Grotesk | Berthold — [bertholdtypes.com](https://bertholdtypes.com/) | Grotesca suiza histórica | Archivo |
| Suisse Int’l | Swiss Typefaces — [swisstypefaces.com](https://www.swisstypefaces.com/fonts/suisse/) | Neo‑grotesca suiza de interfaz | Hanken Grotesk |
| GT America | Grilli Type — [grillitype.com](https://www.grillitype.com/typeface/gt-america) | Cruce grotesca americana/europea, muchos anchos | Archivo (eje de ancho) |
| ABC Diatype | Dinamo — [abcdinamo.com](https://abcdinamo.com/typefaces/diatype) | Grotesca de interfaz con mono a juego | Geist / Familjen + Commit Mono |
| DIN Next | Monotype (Akira Kobayashi) — [monotype.com](https://www.monotype.com/fonts/din-next) | Rotulación técnica/vial alemana | Barlow, Overpass |

### Inferences
- **¿Mantener IBM Plex?** Es técnicamente sólida (cifras tabulares por defecto, mono y serif hermanas, OFL), pero es identidad corporativa de IBM y muy vista en dashboards técnicos; transmite “IBM/consola” más que “parqueadero/universidad”. Recomendable conservar **Plex Mono** solo en una propuesta (Industrial) o sustituirla; para identidad propia, Barlow/Overpass conectan mejor con señalización vial.
- **Por qué Inter y Geist leen como “por defecto”**: Inter fue el default de `create-next-app` hasta 2024 y Geist lo es ahora; ambas son las tipografías de la plantilla que miles de proyectos no cambian, por lo que comunican “plantilla” más que marca. Evitar como primarias.
- Para tablas de dinero/hora en español: usar `font-variant-numeric: tabular-nums` (requiere `tnum`, o cifras tabulares por defecto). Fraunces e Instrument Serif deben quedar fuera de celdas numéricas. Space Grotesk y Bricolage no tienen itálica (o no la exponen) — afecta énfasis en textos largos.
- Si se quiere el cero barrado (`zero`) o estilos alternativos de Plex, autoalojar desde el repo de IBM, porque Google Fonts los elimina.
- El símbolo **$** y separadores de miles (`.` en Colombia) están en Basic Latin, cubiertos por todas.

### Gaps
- No re‑verifiqué en esta sesión años de lanzamiento ni diseñadores de las familias de Google Fonts (los enlaces están dados para verificación); el JSON de metadatos de Google Fonts (2,7 MB) no se pudo leer completo.
- Precios de licencias comerciales (Klim, Commercial Type, Grilli, Dinamo, Monotype) no consultados: marcar solo como “de pago, consultar fundición”.
- Funciones no expuestas por Google Fonts en otras familias (p. ej. `zero` en Inter, `ss` en Barlow) no comprobadas en los archivos originales salvo IBM Plex.
- Atkinson Hyperlegible Mono y Departure Mono no se midieron en archivo original.

---

## 2. Pares tipográficos por dirección de arte

### Takeaway
Se proponen 7 pares libres, uno por dirección de arte pedida, priorizando que la fuente de texto/datos tenga `tnum` o cifras tabulares y que el par no use Inter/Geist como protagonistas.

### Cited Findings
(Las propiedades de cada fuente provienen de la Tabla A — [Fontsource API](https://api.fontsource.org/v1/fonts) + verificación propia; los vínculos con señalización vial de [Barlow GitHub](https://github.com/jpt/barlow) y [Overpass, Wikipedia](https://en.wikipedia.org/wiki/Overpass_(typeface)).)

| # | Dirección | Titulares | Texto/UI | Datos/números | Por qué tiene personalidad | Riesgos |
|---|---|---|---|---|---|---|
| 1 | **Suiza internacional** | Schibsted Grotesk 700–900 | Schibsted Grotesk 400 | Schibsted con `tnum` / JetBrains Mono | Grotesca de periódico nórdico, más tensión que Inter; 6 pesos variables | Sin mono propia |
| 2 | **Editorial** | Fraunces (eje óptico/“wonk”) o Instrument Serif | Source Serif 4 o Newsreader | Source Serif 4 (cifras tabulares por defecto) | Serif con carácter en display; texto serif tabular permite tablas | Fraunces/Instrument Serif sin `tnum`: solo títulos |
| 3 | **Industrial / técnico** | Barlow Condensed 600–800 (mayúsculas) | Barlow 400–500 | IBM Plex Mono o JetBrains Mono | ADN de señal vial (DIN/Highway Gothic/placa de California) | Barlow no es variable: cargar solo 3–4 pesos |
| 4 | **Neo‑brutalismo** | Space Grotesk 700 / Bricolage Grotesque 800 | Space Grotesk 400 | Space Mono o Departure Mono (acentos, códigos de ticket) | Formas excéntricas, mono retro; sistema Space emparentado | Space Grotesk sin itálica; Departure Mono solo para detalles |
| 5 | **Minimalismo tipográfico** | Familjen Grotesk 600 | Familjen Grotesk 400 | Familjen (cifras tabulares por defecto) + Commit Mono | Una sola familia con contraste sutil y cifras ya alineadas | Solo 400–700 |
| 6 | **Retro‑modernismo institucional** | Overpass 800 o Archivo (eje de ancho, expandido) | Public Sans 400 | Public Sans `tnum` / Overpass Mono | Overpass = Highway Gothic (rótulos de autopista); Public Sans = lenguaje de gobierno USWDS | Puede verse “gubernamental” si no se dosifica el color |
| 7 | **Hecho a mano / cálido** | Bricolage Grotesque (opsz, ancho) | Atkinson Hyperlegible Next | Atkinson Hyperlegible Mono | Bricolage tiene trazos irregulares tipo letrero pintado; Atkinson aporta accesibilidad demostrable | Mezcla de dos personalidades fuertes: limitar Bricolage a H1/H2 |

Alternativa de alta accesibilidad aplicable a cualquier propuesta: sustituir la fuente de texto por **Atkinson Hyperlegible Next** (distinción de caracteres ambiguos, >150 idiomas) — [Braille Institute](https://www.brailleinstitute.org/freefont/).

### Inferences
- Para un parqueadero, los pares 3 (Barlow) y 6 (Overpass/Public Sans) son los que mejor justifican la elección con un argumento de dominio (tipografía vial).
- El par 5 (Familjen) es la opción más económica en peso de página (una familia variable).
- Mantener un **máximo de 2 familias + 1 mono** por propuesta y cargar solo subsets `latin` (el español no necesita `latin-ext`).

### Gaps
- No hay datos de rendimiento (KB por archivo) medidos en esta sesión.
- Ningún “Fonts In Use” ni Typewolf consultado sobre estos pares concretos; los emparejamientos son criterio propio basado en las fichas técnicas.

---

## 3. Paletas de color (señalización vial, institucional, papel/tinta), con roles y contraste WCAG 2.2

### Takeaway
Seis paletas (más una variante oscura) superan AA en todos los pares de texto definidos. Hallazgos críticos: (a) el **amarillo señal nunca pasa como texto sobre claro** (1.78:1) — solo como relleno con texto tinta (9.27:1); (b) los **bordes decorativos claros** (≈1.3–1.5:1) no sirven para campos de formulario, que necesitan ≥3:1 (WCAG 1.4.11); (c) con un rojo “puro” de peligro y un naranja/ámbar de aviso, **aviso y peligro se confunden en deuteranopía** (ΔE 1.7–9.5); se corrige empujando el peligro hacia el carmín/magenta (#B4234A → ΔE 30–33).

### Cited Findings

**Normas de referencia**
- Umbrales: texto normal ≥4.5:1, texto grande (≥24 px o ≥18.5 px en negrita) ≥3:1, AAA 7:1; componentes UI y gráficos 3:1 (1.4.11); fórmula de luminancia con umbral 0.04045 (antes de 2021, 0.03928, sin efecto práctico) — [W3C WCAG 2.2 Understanding 1.4.3](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html).
- MUTCD/FHWA: los colores de señales se especifican como cajas de cromaticidad CIE 1931 (iluminante D65 de día, A de noche) en el apéndice de 23 CFR 655 Subparte F; no hay hex oficiales, y la FHWA advierte que espectrofotómetros convencionales no deben usarse para materiales retrorreflectivos — [Federal Register 2002](https://www.govinfo.gov/content/pkg/FR-2002-07-31/html/02-19140.htm), [Cornell LII, 23 CFR 655 App.](https://www.law.cornell.edu/cfr/text/23/appendix-to_subpart_F_of_part_655).
- RAL: los hex son **aproximaciones** y varían por fuente. RAL 1003 amarillo señal: #F7BA0B (W3Schools), #F9A800, #F2A900 (Siemens), #F2AD00 (hextoral); RAL 3020 rojo tráfico: #C1121C / #BF111B / #B81D13; RAL 5017 azul tráfico: #0E518D / #004C91 / #005A8C; RAL 6024 verde tráfico: #008754 / #008455 — [W3Schools RAL](https://www.w3schools.com/colors/colors_ral.asp), [Siemens Solid Edge RAL→RGB](https://support.sw.siemens.com/en-US/okba/PL7006008/RAL-Classic-color-palette-to-Solid-Edge-RGB-colours/index.html), [Diotti RAL](https://www.diotti.com/de/colori-ral2). Wikipedia recomienda la carta física RAL para muestras vinculantes.
- Colombia: el nuevo Manual de Señalización Vial fue adoptado por la Resolución 20243040045005 del 17‑sep‑2024 (reemplaza el de 2015) y rige desde el 1‑abr‑2025 — [Implementando SGI](https://www.implementandosgi.com/normatividad/resolucion-20243040045005-de-2024/), [Blu Radio](https://www.bluradio.com/motor/nuevas-senales-de-transito-en-colombia-so35). Convención de colores (fuentes secundarias): **preventivas** rombo fondo amarillo con símbolo negro; **reglamentarias** círculo fondo blanco, borde rojo, símbolo negro; **informativas** rectangulares con fondo azul, verde, café, blanco o rojo — [OSM Wiki Colombia señales](https://wiki.openstreetmap.org/wiki/ES:Colombia/Guide_for_mapping_traffic_signs), [Portafolio](https://www.portafolio.co/economia/finanzas/conocimientos-minimos-que-debe-tener-sobre-senalizacion-vial-533965). Manual 2015 PDF: [Alcaldía de Medellín](https://www.medellin.gov.co/es/wp-content/uploads/2023/04/ManualdeSenalizacionVial2015.pdf).

**Paletas** (todos los ratios calculados por mí con la fórmula W3C citada arriba; “accentText” = versión del acento apta para texto).

#### P1 — Señal vial (evolución de la UI actual: tinta + amarillo)
| Rol | Hex |
|---|---|
| fondo | `#F6F4EE` |
| superficie | `#FFFFFF` |
| texto | `#14161A` |
| atenuado | `#5B5F66` |
| borde decorativo / borde de campo | `#D6D2C6` / `#8A8578` |
| acento (relleno) | `#F2AD00` (≈RAL 1003) |
| acento como texto | `#7A5500` |
| éxito / aviso / peligro / info | `#1E7A4C` / `#A65300` / **`#B4234A`** (corregido; el original `#B3261E`) / `#0E518D` (≈RAL 5017) |

| Par | Ratio | Nivel |
|---|---|---|
| texto / fondo | 16.47 | AAA |
| texto / superficie | 18.11 | AAA |
| atenuado / fondo | 5.83 | AA |
| texto tinta / relleno amarillo | 9.27 | AAA |
| amarillo / fondo (como texto) | 1.78 | **FALLA** |
| acento‑texto `#7A5500` / fondo | 6.11 | AA |
| borde de campo `#8A8578` / fondo | 3.35 | ≥3:1 UI |
| éxito / blanco | 5.33 | AA |
| aviso / blanco | 5.44 | AA |
| peligro `#B3261E` / blanco | 6.54 | AA |
| peligro `#B4234A` / blanco | 6.40 | AA |
| info / blanco | 8.14 | AAA |

Daltonismo (aviso `#A65300` vs peligro): con `#B3261E` deutan ΔE=9.5 (se confunden); con `#B4234A` deutan ΔE=32.8, protan ΔE=46.9 (separables). Éxito‑peligro protan ΔE≈17 (aceptable, reforzar con icono).

#### P1d — Señal vial, variante oscura
| Rol | Hex |
|---|---|
| fondo / superficie | `#111317` / `#1B1E24` |
| texto / atenuado | `#ECEAE4` / `#A3A7AE` |
| borde decorativo / de campo | `#33373F` / `#6B7280` |
| acento | `#F2B705` |
| éxito / aviso / peligro / info | `#4CC38A` / `#F59E3B` / `#FF7A6E` / `#6CB4F5` |

| Par | Ratio | Nivel |
|---|---|---|
| texto / fondo | 15.46 | AAA |
| texto / superficie | 13.88 | AAA |
| atenuado / superficie | 6.91 | AA |
| amarillo / superficie (texto) | 9.18 | AAA |
| fondo tinta sobre amarillo (botón) | 10.23 | AAA |
| texto claro sobre amarillo | 1.51 | **FALLA** (en botones amarillos el texto siempre tinta) |
| borde de campo `#6B7280` / superficie | 3.45 | ≥3:1 |
| éxito / aviso / peligro / info sobre superficie | 7.54 / 7.82 / 6.57 / 7.54 | AA–AAA |

Daltonismo oscuro: éxito‑peligro protan ΔE=14.3, deutan 20.5 → bajo; usar icono + texto obligatorio, o mover peligro a `#FF6B9A`‑rosado (no calculado).

#### P2 — Azul tránsito institucional (señales informativas)
| Rol | Hex |
|---|---|
| fondo / superficie | `#F3F5F8` / `#FFFFFF` |
| texto / atenuado | `#0B1B2B` / `#4E5B6A` |
| borde decorativo / de campo | `#CBD3DD` / `#7D8794` |
| acento (≈RAL 5017) | `#004C91` |
| éxito / aviso / peligro / info | `#007A4D` / `#9A5B00` / `#BF111B` (≈RAL 3020) → recomendado `#B4234A` / `#004C91` |

| Par | Ratio | Nivel |
|---|---|---|
| texto / fondo | 15.94 | AAA |
| atenuado / fondo | 6.35 | AA |
| acento / fondo (texto, enlaces) | 7.87 | AAA |
| blanco sobre acento (botón) | 8.60 | AAA |
| borde de campo / fondo | 3.34 | ≥3:1 |
| éxito / aviso / peligro sobre blanco | 5.40 / 5.43 / 6.34 | AA |

Daltonismo: aviso `#9A5B00` vs peligro `#BF111B` deutan ΔE=**4.4** (indistinguibles); con `#B4234A` ΔE=31.8.

#### P3 — Papel y tinta editorial
| Rol | Hex |
|---|---|
| fondo (papel) / superficie | `#F3EEE3` / `#FBF8F1` |
| texto (tinta) / atenuado | `#1F1B16` / `#6B6257` |
| borde decorativo / de campo | `#D9D0BF` / `#8E8578` |
| acento (rojo bermellón) / como texto | `#B8321E` / `#A42C1A` |
| éxito / aviso / peligro / info | `#2F6B3A` / `#8C5A00` / `#A3215B` (recomendado) / `#2C4F7C` |

| Par | Ratio | Nivel |
|---|---|---|
| texto / fondo | 14.80 | AAA |
| atenuado / fondo | 5.17 | AA |
| acento / fondo | 5.17 | AA |
| blanco sobre acento | 5.98 | AA |
| borde de campo / fondo | 3.14 | ≥3:1 |
| éxito / aviso / info sobre superficie | 6.03 / 5.53 / 7.87 | AA–AAA |
| peligro `#A3215B` / blanco | 7.18 | AAA |

Daltonismo: con peligro = acento `#A42C1A` aviso‑peligro deutan ΔE=7.7 (malo); con `#A3215B` deutan ΔE=45.1, protan 61.2. Nota: si el acento de marca es rojo, separar visualmente “acento” de “error” (el error siempre con icono).

#### P4 — Hormigón y naranja de seguridad (industrial)
| Rol | Hex |
|---|---|
| fondo (hormigón) / superficie | `#E7E6E1` / `#F5F5F2` |
| texto / atenuado | `#1A1A1A` / `#55554F` |
| borde decorativo / de campo | `#BDBCB4` / `#85847C` |
| acento naranja (relleno) / como texto | `#E8590C` / `#A63F00` |
| éxito / aviso / peligro / info | `#26734D` / `#8F5E00` / `#B4234A` (recomendado; original `#B42318`) / `#1F5FAD` |

| Par | Ratio | Nivel |
|---|---|---|
| texto / fondo | 13.93 | AAA |
| atenuado / fondo | 6.00 | AA |
| texto tinta / naranja | 4.86 | AA |
| blanco / naranja | 3.58 | solo texto grande/UI |
| naranja / fondo (texto) | 2.86 | **FALLA** |
| acento‑texto `#A63F00` / superficie | 5.77 | AA |
| borde de campo / fondo | 3.01 | ≥3:1 (justo) |
| éxito / aviso / peligro / info sobre superficie | 5.28 / 5.10 / 6.02 / 5.83 | AA |

Daltonismo: aviso vs `#B42318` deutan ΔE=**3.95**; con `#B4234A` ΔE=30.0.

#### P5 — Neo‑brutal amarillo/negro
| Rol | Hex |
|---|---|
| fondo / superficie | `#FFFDF5` / `#FFFFFF` |
| texto / atenuado | `#000000` / `#3D3D3D` |
| borde (grueso, 2–3 px) | `#000000` |
| acento | `#FFD400` |
| éxito / aviso / peligro / info | `#007F3F` / `#B34700` / `#C2185B` (recomendado; original `#D10000`) / `#0047FF` |

| Par | Ratio | Nivel |
|---|---|---|
| texto / fondo | 20.62 | AAA |
| atenuado / fondo | 10.67 | AAA |
| negro / amarillo | 14.67 | AAA |
| amarillo / fondo (texto) | 1.41 | **FALLA** |
| borde / fondo | 20.62 | AAA |
| éxito / aviso / info sobre blanco | 5.11 / 5.50 / 6.28 | AA |
| peligro `#C2185B` / blanco | 5.87 | AA |

Daltonismo: aviso `#B34700` vs `#D10000` deutan ΔE=**1.67** (idénticos); con `#C2185B` ΔE=39.2.

#### P6 — Niebla y bosque (enmarca la imagen del login)
| Rol | Hex |
|---|---|
| fondo / superficie | `#EEF2EF` / `#FFFFFF` |
| texto / atenuado | `#16261E` / `#4F5F57` |
| borde decorativo / de campo | `#C9D3CD` / `#7E8C84` |
| acento (verde bosque) | `#2F6B4F` |
| complementarios de la ilustración | rosa plaza `#E7A1B0`, cielo `#9CC3E6` (solo superficies/fondos) |
| éxito / aviso / peligro / info | `#2F6B4F` / `#9A5A00` / `#B4234A` / `#2E5E9E` |

| Par | Ratio | Nivel |
|---|---|---|
| texto / fondo | 13.97 | AAA |
| atenuado / fondo | 5.98 | AA |
| acento / fondo | 5.57 | AA |
| blanco / acento (botón) | 6.29 | AA |
| texto / rosa | 7.61 | AAA |
| texto / cielo | 8.54 | AAA |
| texto verde‑tinta / amarillo `#F2AD00` | 8.08 | AAA |
| borde de campo / fondo | 3.11 | ≥3:1 |
| aviso / peligro / info sobre blanco | 5.47 / 6.40 / 6.54 | AA |

Daltonismo: aviso‑peligro deutan ΔE=31.6, protan 47.8 (bien); **éxito‑peligro deutan ΔE=14.1, protan 16.1** (bajo: verde y carmín se acercan) → obligatorio icono/forma (✓, ▲, ✕) y etiqueta textual.

### Inferences
- **Patrón general**: en paletas inspiradas en señalización, el rojo “de tráfico” y el naranja/ámbar quedan casi idénticos para deuteranopes. La solución reproducible es: aviso = ámbar oscuro (`#8C5A00`–`#A65300`), peligro = carmín con componente azul (`#A3215B`–`#C2185B`), y nunca codificar estado solo con color (icono + texto, WCAG 1.4.1).
- El amarillo de señal solo debe usarse como **relleno** (botón primario, franja, badge de “Disponible/Pago pendiente”) con texto tinta; para texto amarillo, solo sobre fondo oscuro (P1d).
- Para la variante oscura de cualquier paleta: subir luminosidad de estados (~L* 65–75), evitar negro puro (#000) de fondo, y no reutilizar el borde decorativo como borde de campo.
- Mapeo sugerido a direcciones: P1/P1d → Industrial e Institucional; P2 → Institucional/Suiza; P3 → Editorial/Hecho a mano; P4 → Industrial; P5 → Neo‑brutalismo; P6 → Minimalismo/Hecho a mano y login.

### Gaps
- No se obtuvieron coordenadas cromáticas oficiales del Manual de Señalización Vial colombiano 2024 (las fuentes de prensa no las detallan); los hex “vial” son aproximaciones RAL, no colores normativos.
- La simulación de daltonismo es computacional (Machado 2009 + ΔE76); no se probó con usuarios ni con herramientas como Sim Daltonism/Coblis.
- Los valores corregidos para P1d (peligro rosado) no se calcularon.

---

## 4. Armonizar la UI con el fondo ilustrado difuminado del login (verde, azul, rosa, ciudad)

### Takeaway
Sobre la imagen borrosa, el texto debe ir en una tarjeta con **velo** (capa semitransparente): un velo blanco del 75–85 % mantiene el texto tinta en 13–16:1 y el atenuado ≥4.5:1 sobre cualquiera de los tonos de la ilustración; al 60 % el atenuado falla sobre verdes y grises de ciudad. En modo oscuro, un velo tinta del 70 % ya da ≥7.8:1.

### Cited Findings
Cálculo propio con la fórmula W3C ([Understanding 1.4.3](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html)); colores base son estimaciones representativas de la ilustración (verde árbol `#4E8A4A`/`#6FA86A`, cielo `#8EC5E8`, plaza rosa `#E8A0B4`, ciudad `#3A4A5A`), no muestreados de la imagen real:

| Base | Velo blanco 60 % | 75 % | 85 % |
|---|---|---|---|
| verde oscuro `#4E8A4A` | texto 10.99 / atenuado **3.89** | 13.44 / 4.76 | 15.12 / 5.36 |
| verde claro `#6FA86A` | 12.42 / **4.40** | 14.39 / 5.10 | 15.82 / 5.60 |
| cielo `#8EC5E8` | 14.33 / 5.08 | 15.71 / 5.56 | 16.58 / 5.87 |
| rosa `#E8A0B4` | 13.74 / 4.87 | 15.24 / 5.40 | 16.41 / 5.81 |
| ciudad `#3A4A5A` | 8.93 / **3.16** | 11.91 / **4.22** | 14.18 / 5.02 |

(texto = `#14161A`, atenuado = `#5B5F66`). Velo tinta `#111317` con texto `#ECEAE4`: 70 % → 10.61 (verde), 7.85 (cielo), 8.25 (rosa); 85 % → 11.5–13.1.

### Inferences
- Regla práctica: tarjeta de login con `background: rgb(255 255 255 / .85)` (o tinta `/ .8` en oscuro) + `backdrop-filter: blur()`; el texto atenuado no debe ir directamente sobre la imagen.
- Estrategias cromáticas: **armonizar** (P6: verde bosque como acento, rosa y cielo en superficies secundarias) o **enmarcar deliberadamente** (P1/P5: tinta + amarillo señal como contraste complementario frente a la imagen fría y verde; el amarillo resalta sobre verdes/azules por oposición de tono). Evitar acentos turquesa/azul medio que se pierden contra el cielo.
- El rosa de la plaza puede funcionar como color de “selección/destacado” no semántico, nunca como peligro (se confundiría con el carmín recomendado).

### Gaps
- No se muestreó la imagen real del proyecto; repetir el cálculo con los colores medios de la imagen difuminada (p. ej. con un cuentagotas sobre la versión blur) antes de fijar la opacidad del velo.
- No se evaluó contraste de texto sobre degradados/zonas mixtas (WCAG exige evaluar el peor punto).
