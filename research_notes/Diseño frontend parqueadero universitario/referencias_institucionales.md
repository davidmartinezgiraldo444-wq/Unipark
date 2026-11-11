# Referencias web institucionales (universidades, museos, sector público) con identidad visual fuerte

Fecha de verificación: 2026-11-08/09. Método: (1) descarga directa del HTML y de las hojas de estilo de cada sitio con curl y extracción de `font-family`, nombres de archivos `.woff/.woff2`, colores hex más frecuentes, `border-radius` y `box-shadow`. (2) Capturas de pantalla en navegador de 6 sitios (Yale School of Art, GOV.UK, ECAL, Rietveld, Whitney, HfG Karlsruhe). Para ETH, Kunstmuseum Basel, Design Museum y EPFL+ECAL Lab la navegación funcionó pero la captura no se pudo leer, así que su descripción visual sale solo del CSS. (3) Búsqueda de fichas en galerías (SiteInspire, Awwwards, Fonts In Use).

Cómo leer la evidencia: "CSS" significa que la fuente o el color aparecen en la hoja de estilo publicada del propio sitio (URL indicada). Los hex se listan por frecuencia de aparición en el CSS, no son una paleta oficial. Las URLs de hojas de estilo con hash cambian en cada despliegue.

## Pregunta 1 — Qué sitios reales sirven de referencia y dónde están catalogados (galería + disponibilidad)

### Takeaway
Hay 10 sitios institucionales verificados en línea con identidad tipográfica propia y comprobable por CSS. Solo 4 tienen ficha de galería confirmada (SiteInspire: Yale University Art Gallery, Art Institute of Chicago, ECAL Typefaces; Awwwards: EPFL+ECAL Lab). Los demás se justifican por fuentes primarias: CSS del sitio, página de identidad del propio museo o del estudio que lo hizo. SiteInspire bloqueó la descarga automática (HTTP 429, "Vercel Security Checkpoint"), así que sus fichas se confirmaron solo por resultados de búsqueda y no abriendo la página.

### Cited Findings
**Lista vetada (todas respondieron HTTP 200 o cargaron en navegador el 2026-11-08/09):**

1. **Yale School of Art**: https://art.yale.edu. Es un wiki que la comunidad puede editar: "every graduate student, staff person, and faculty member of the School can change this website's content". Fuentes: [About this site](https://www.art.yale.edu/about-this-site) y [Guía de módulos, alojada también en linkedbyair.net](https://art.linkedbyair.net/about/about-this-site/a-guide-to-magic-modules), que indica que el estudio Linked by Air está detrás. Galería: no encontré ficha en SiteInspire ni en Awwwards.
2. **GOV.UK + GOV.UK Design System**: https://www.gov.uk y https://design-system.service.gov.uk (los dos con HTTP 200). Galería: no encontré ficha. Su referencia primaria es la documentación pública del sistema de diseño, por ejemplo [Table](https://design-system.service.gov.uk/components/table/).
3. **ECAL (École cantonale d'art de Lausanne)**: https://ecal.ch, que redirige a https://ecal.ch/fr/. Galería relacionada: [SiteInspire, ECAL Typefaces](https://www.siteinspire.com/website/5595-ecal-typefaces) (es la fundición tipográfica, no el sitio principal) y [Fonts In Use, Renens](https://fontsinuse.com/typefaces/134611/renens).
4. **Gerrit Rietveld Academie**: https://rietveldacademie.nl. Galería: no encontré ficha del sitio web. Fonts In Use solo cataloga piezas impresas de la academia ([ejemplo](https://fontsinuse.com/uses/72438/school-life-daily-life-2025-2026)).
5. **Whitney Museum of American Art**: https://whitney.org. La identidad es de Experimental Jetset (2013) y el sitio de Linked by Air. Fuentes: [whitney.org/newidentity](https://whitney.org/newidentity) y [Linked by Air, proyectos Whitney](https://www.linkedbyair.net/projects/client/37-whitney-museum-of-american-art). Galería: no encontré ficha.
6. **HfG Karlsruhe (Staatliche Hochschule für Gestaltung)**: https://www.hfg-karlsruhe.de, que redirige a hfg-karlsruhe.de. Su declaración de accesibilidad anunciaba un relaunch "planned for 15.9.2026" ([Barrierefreiheit](https://hfg-karlsruhe.de/en/barrierefreiheit/)). El 2026-11-09 el sitio seguía mostrando la plantilla con Theinhardt y Executive que se describe abajo, así que el relaunch no está confirmado o fue parcial. Galería: no encontré ficha.
7. **ETH Zürich**: https://ethz.ch/en.html. Galería: no encontré ficha.
8. **Kunstmuseum Basel**: https://www.kunstmuseumbasel.ch/en, que redirige a kunstmuseumbasel.ch/en. Galería: no encontré ficha, y no identifiqué el estudio que hizo el sitio. Ojo: Porto Rocha hizo la Kunsthalle Basel, que es otra institución ([bpando](https://bpando.org/2026/01/27/kunsthalle-basel-art-museum-branding-by-porto-rocha/)).
9. **EPFL+ECAL Lab**: https://www.epfl-ecal-lab.ch. Galería: [Awwwards, EPFL+ECAL Lab, Honorable Mention (junio 2023)](https://www.awwwards.com/sites/epfl-ecal-lab) y [Awwwards Inspiration, "Proportional grid system"](https://www.awwwards.com/inspiration/proportional-grid-system-epfl-ecal-lab).
10. **Design Museum (Londres)**: https://www.designmuseum.org, que redirige a designmuseum.org. Galería: no encontré ficha.

**Candidatos de reserva o descartados:**
- **Art Institute of Chicago**: https://www.artic.edu (HTTP 200). Tiene ficha en [SiteInspire](https://www.siteinspire.com/website/8423-the-art-institute-of-chicago). CSS: `font-family: 'Sabon'` y archivos con prefijo `3545D5_*.woff2`, nomenclatura típica de MyFonts/Monotype. Es una buena reserva editorial con serif.
- **Yale University Art Gallery** (artgallery.yale.edu): tiene ficha en [SiteInspire](https://www.siteinspire.com/website/906-yale-university-art-gallery), pero curl recibió 403 (protección Cloudflare). La fuente queda sin verificar.
- **MIT Media Lab** (https://www.media.mit.edu): curl devolvió 200, pero en el navegador la carga falló. Carga el kit de Typekit `use.typekit.net/yuf7oeh.css`, que declara `font-family:"neue-haas-grotesk-display"`. Es candidato de reserva y no lo pude analizar visualmente.
- **Cooper Hewitt** y **MoMA**: 403 por Cloudflare desde curl. **Stedelijk**: 202 con cuerpo vacío. **Parsons**: 429. Los cuatro quedan sin verificar y no deben usarse sin revisión manual.
- **Bauhaus Dessau** (https://www.bauhaus-dessau.de/en/): HTTP 200. El HTML trae `font-family:'BundesSans Cond'` y un archivo `Friedl-Beta1_240227-VF.ttf`. No se pudo hacer captura, así que es una reserva débil.
- **HfG Offenbach**: CSS con `Helvetica`, `FontAwesome` y `border-radius:50%`/`5px`. Lo descarto por genérico.
- **RCA (Royal College of Art)**: CSS con `Source Sans 3`, archivos `latin-400-normal.woff2`, acento `#ff5d1c`. Es correcto pero poco distintivo, y lo dejo como reserva.

### Inferences
- Encontrar el sitio en una galería no es la mejor prueba de "identidad humana". En las instituciones fuertes (Whitney, Yale, ECAL) la evidencia más sólida es la página de identidad del propio museo o la ficha del estudio.

### Gaps
- No pude abrir directamente fichas de SiteInspire (bloqueo 429) ni encontrar fichas en Land-book, Godly, Httpster o Minimal Gallery para estos sitios. Las búsquedas restringidas a esos dominios no las devolvieron.
- No hay entradas de Fonts In Use para los sitios web en sí, solo para piezas impresas o para las tipografías.

## Pregunta 2 — Análisis visual y tipográfico por sitio (tipografía con evidencia, jerarquía, paleta, retícula, componentes, detalles "hechos a mano")

### Takeaway
El patrón común de los mejores ejemplos es este: una tipografía corporativa propia o de fundición (Renens, Yale New + Univers, GDS Transport, Theinhardt, DINPro, Neue Haas Grotesk), paletas casi monocromas negro/blanco con un solo acento saturado, `border-radius:0` dominante y casi nada de sombras. Para la UI de un parqueadero universitario, los modelos más transferibles son GOV.UK (formularios, tablas, foco amarillo), ETH (sistema universitario sobrio con DIN) y HfG Karlsruhe (fechas grandes, monoespaciada para metadatos).

### Cited Findings

#### 1. Yale School of Art — https://art.yale.edu
- **Tipografía (CSS):** `font-family: Yale New` y `Univers`. Archivos `yalenew-roman-webfont.woff2`, `yalenew-italic-webfont.woff2`, `univers-55-roman.woff2` y `univers-55-oblique.woff2`, más variables `--font-yale` y `--font-univers` — [CSS entry.B5iVlZGs.css](https://art.yale.edu/_nuxt/entry.B5iVlZGs.css), consultado el 2026-11-08.
- **Paleta (CSS, por frecuencia):** #fff, #000, #fef212 (amarillo), #999, #232323, #00f, #00a128, #0051e8.
- **Bordes y sombras (CSS):** `border-radius:0` y `box-shadow:none` predominan. El CSS incluye utilidades Tailwind (`--tw-inset-shadow`).
- **Observado en captura (2026-11-09):** página casi vacía y muy blanca. Un enlace "Menu" en rojo arriba a la izquierda, el nombre "YALE SCHOOL OF ART" centrado en versalitas serif (Yale New) y un bloque de texto en gris muy claro, centrado en una columna estrecha. Hay un componente "TickerController" en el CSS (texto en marquesina).
- **Identidad humana:** es un wiki editable por estudiantes y profesores ([About this site](https://www.art.yale.edu/about-this-site)). La identidad sale de la contribución colectiva y no de una plantilla.
- **Estilos:** Minimalismo tipográfico + Handcrafted.

#### 2. GOV.UK / GOV.UK Design System — https://www.gov.uk
- **Tipografía (CSS):** `font-family:"GDS Transport",arial,sans-serif`, con 196 apariciones. Archivos `light-…woff2` y `bold-…woff2`, es decir, solo dos pesos: light (400) y bold (700) — [CSS application](https://www.gov.uk/assets/frontend/application-5c3385e08fe8537ee8f94db512cdc8e15439427843360d44fb88cfdac0fadfe1.css).
- **Paleta (CSS):** #0b0c0c (texto casi negro, 359 apariciones), #fff, #ffdd00 (amarillo de foco), #cecece, #1a65a6/#1d70b8 (azul de enlaces y marca), #f3f3f3 (fondos grises). El foco se marca con `box-shadow:0 -2px var(--govuk-focus-colour, #ffdd00)`: es el único uso funcional de "sombra", como subrayado grueso.
- **Bordes (CSS):** `border-radius:0` con radios parametrizados por variables (`--button-bo…`, `--panel-bor…`).
- **Observado en captura:** banner de cookies con botones verdes rectangulares ("Accept/Reject additional cookies") y un enlace subrayado. Cabecera azul con la corona y "GOV·UK", y un titular enorme en bold blanco ("The best place to find government services and information"). Buscador con input blanco sin bordes redondeados y un botón cuadrado con lupa pegado al input.
- **Componentes transferibles:** tablas, formularios y mensajes de error documentados en el [Design System, Table](https://design-system.service.gov.uk/components/table/).
- **Estilos:** Swiss International + Minimalismo tipográfico, en clave utilitaria y cívica.

#### 3. ECAL — https://ecal.ch
- **Tipografía (CSS):** `font-family:Renens,Helvetica,Arial,sans-serif`. Archivos `Renens2021-Light`, `Renens2021-LightItalic`, `Renens2021-Medium` y `Renens2021-MediumItalic` (.woff y .woff2) — [CSS module.css](https://ecal.ch/static/build/site/module.css?v=5nu1zamf7). Renens es la tipografía corporativa de ECAL, diseñada por François Rappo y no disponible para licencia — [Fonts In Use, Renens](https://fontsinuse.com/typefaces/134611/renens).
- **Paleta (CSS):** #fff, #000, #46789a, #f3f3f3, #e6ba1a, #dc0f0e, #d4e926, #959595. Base negro/blanco con varios acentos.
- **Bordes (CSS):** `border-radius:50%` (9 apariciones) junto a `border-radius:0` (7). `box-shadow:none` domina.
- **Observado en captura:** logotipo "éc a l" con letras muy espaciadas arriba a la izquierda. Menú horizontal en mayúsculas pequeñas, en negrita y subrayado por una línea fina (ADMISSIONS, FORMATIONS & RECHERCHE, VIE ETUDIANTE, L'ECOLE, FEED), selector FR/EN en caja negra. Debajo, un hero a todo ancho color burdeos con un collage, el titular "Formations en art & design depuis 1821" y una marquesina de texto con separadores "|". Botones de acceso rápido en forma de **elipse con contorno negro** (ADMISSIONS >, FORMATIONS >, FEED >, SHOP >).
- **Identidad humana:** la tipografía propia, la marquesina y los botones ovalados de contorno fino.
- **Estilos:** Swiss International + Editorial.

#### 4. Gerrit Rietveld Academie — https://rietveldacademie.nl
- **Tipografía (CSS):** `font-family:"new rail",times`. Archivos `NewRailAlphabet-LightWEB.woff2` y `NewRailAlphabet-LightItalicWEB.woff2`, más una fuente de iconos `icomoon` — [CSS screen](https://rietveldacademie.nl/lib/dist/screen~166070362.css). New Rail Alphabet es de A2-TYPE, una reinterpretación del Rail Alphabet de Margaret Calvert ([A2-TYPE](https://a2-type.co.uk/new-rail-alphabet)).
- **Paleta (CSS):** #000 (64), #fff (37), #00f (25, azul puro) y #a94442 (rojo de error, Bootstrap). Monocromo con azul eléctrico.
- **Bordes y sombras (CSS):** `border-radius:0` (13 apariciones), `box-shadow:none`. Usa Bootstrap como base (`/lib/bootstrap/css/bootstrap.min…css`).
- **Observado en captura:** cabecera mínima ("Gerrit Rietveld Academie" a la izquierda, "Apply" subrayado, lupa, NL y Menu a la derecha). Dos grandes llamadas a la acción dibujadas como **elipses de trazo fino** que ocupan todo el ancho ("Apply", "Book the front-space"). Debajo, la etiqueta "Calendar" en cuerpo pequeño y una retícula de 3 columnas de tarjetas con imagen, fecha y título, sin bordes ni sombras.
- **Identidad humana:** las elipses dibujadas a mano alzada como botones y una sola tipografía light para todo.
- **Estilos:** Handcrafted + Minimalismo tipográfico.

#### 5. Whitney Museum — https://whitney.org
- **Tipografía (CSS/HTML):** `font-family:haas-grotesk-text` y `haas-grotesk-sign`. Archivos `Haas_Grot_Text_55_Roman.woff2`, `…56_Italic`, `…75_Bold` y `…76_Bold_Italic` — HTML de inicio y [CSS application](https://whitney.org/assets/application-64097f3d.css). Usa también Font Awesome 7 para iconos.
- **Paleta (CSS):** #ccc, #fff, #000, #d7ff2d (verde-lima ácido), #949494, #666, #f2f2f2.
- **Bordes (CSS):** `border-radius:0` (7), `box-shadow:none`. Los separadores son líneas (`box-shadow:1px 0 #ccc`).
- **Observado en captura:** fondo negro y la palabra "WHITNEY" en gris claro, grotesca grande. La "W responsiva" de Experimental Jetset se dibuja como una línea zigzag fina que atraviesa toda la cabecera. Botones rectangulares con contorno blanco fino ("BOOK TICKETS", "BECOME A MEMBER"), navegación principal en mayúsculas bold (VISIT, WHAT'S ON, ART, LEARN, SHOP) sobre una regla horizontal, e imágenes de obra a sangre con titulares en mayúscula bold.
- **Identidad (fuente primaria):** "a dynamic W that responds to the artworks and words around it" — [whitney.org/newidentity](https://whitney.org/newidentity).
- **Estilos:** Swiss International + Editorial.

#### 6. HfG Karlsruhe — https://www.hfg-karlsruhe.de
- **Tipografía (CSS):** `font-family:"TheinhardtRegular", "HelveticaNeue"…` (52 apariciones) y `"ExecutiveLight", "Courier New"…` (21), una monoespaciada tipo máquina de escribir. Fuente de iconos propia `HfGIconFontMedium.woff2` — [CSS style.css](https://www.hfg-karlsruhe.de/dist/style.css) y [hfgot.css](https://cdn.hfg-karlsruhe.de//dot/hfgot.css).
- **Paleta (CSS):** #fff, #000, #0000ff (azul puro), #d2dcff (azul pálido), #222222, #e7e7e7.
- **Bordes (CSS):** `border-radius: 0px` dominante. Hay un `box-shadow: 10px 10px 5px #a8a8a8` aislado.
- **Observado en captura:** navegación tipo índice en 5 columnas de enlaces en mayúsculas pequeñas monoespaciadas (AKTUELLES, HOCHSCHULE, STUDIUM, VORLESUNGSVERZEICHNIS…). A la derecha, el logotipo con una serie de barras inclinadas "//////". Un calendario horizontal con la etiqueta "TERMINE:", tipo de evento en mono pequeño ("VERANSTALTUNG", "MELDUNG"), **fechas enormes** "NOV 04 / NOV 05 / NOV 10" en grotesca, separadores verticales finos y una miniatura. Pie con botones pequeños negros con texto blanco.
- **Identidad humana:** la mezcla de grotesca y mono de máquina de escribir, y la estructura de índice o tablón de anuncios.
- **Estilos:** Swiss International + Retro-modernismo institucional (mono de máquina de escribir, estética de archivo).

#### 7. ETH Zürich — https://ethz.ch/en.html
- **Tipografía (CSS):** `font-family:"DINPro",helvetica,arial,sans-serif`. Archivos `regular.woff2`, `medium.woff2`, `bold.woff2` y sus itálicas. Iconos con `"Material Icons Outlined"` y `"EthzIcons"` — [CSS clientlibs](https://ethz.ch/etc.clientlibs/ethz/design/clientlibs/css.css).
- **Paleta (CSS):** #fff, #000, #222, #6f6f6f, #e2e2e2, #f1f1f1, #f6f6f6 y #215caf, el azul ETH (20 apariciones).
- **Bordes (CSS):** `border-radius:0` (19) y `2px` (14), `box-shadow:none` casi total.
- **Visual:** la página cargó en el navegador (título "ETH Zurich - Homepage"), pero la captura no se pudo leer. No describo la retícula.
- **Estilos:** Swiss International, en un sistema universitario sobrio.

#### 8. Kunstmuseum Basel — https://www.kunstmuseumbasel.ch/en
- **Tipografía (CSS):** `font-family:MaisonNeue,Arial,Helvetica…`. Archivos `MaisonNeue-Book`, `MaisonNeue-BookItalic`, `MaisonNeue-Bold` y `MaisonNeue-BoldItalic` (.woff/.woff2). Iconos `kmb_icons` — [CSS main.css](https://cdn.kunstmuseumbasel.ch/website/assets/20261022114657-4823/f07a77a/dist/main.css).
- **Paleta (CSS):** #fff (183), #000 (171). El resto (#f0ad4e, #d9534f, #5cb85c, #0275d8) son los colores por defecto de Bootstrap, no de la marca. En la práctica la identidad es negro sobre blanco.
- **Bordes (CSS):** mezcla de `.25rem`/`3px` (heredado de Bootstrap) y `0`.
- **Visual:** la página cargó pero la captura no se pudo leer. No describo la retícula.
- **Estilos:** Minimalismo tipográfico + Swiss.

#### 9. EPFL+ECAL Lab — https://www.epfl-ecal-lab.ch
- **Tipografía (CSS del sitio):** archivos `ESKlarheitGrotesk-Extralight`, `-Medium`, `-Bold` y sus itálicas (.woff2). Es Klarheit Grotesk; el prefijo "ES" apunta a Entype Studio, pero eso no lo verifiqué. **Conflicto:** la ficha de [Awwwards](https://www.awwwards.com/sites/epfl-ecal-lab) enlaza `/fonts/inter-tight` en su sección "Font & Color". Puede que el sitio haya cambiado de fuente después de 2023 o que Awwwards lo registrara mal. La fuente actual según CSS es ES Klarheit Grotesk.
- **Galería:** Awwwards Honorable Mention, más una entrada de inspiración llamada "Proportional grid system" ([Awwwards](https://www.awwwards.com/inspiration/proportional-grid-system-epfl-ecal-lab)), lo que indica que la retícula proporcional es su rasgo destacado.
- **Visual:** la captura no se pudo leer. El título de la página es "Welcome to the EPFL+ECAL Lab".
- **Estilos:** Swiss International.

#### 10. Design Museum (Londres) — https://www.designmuseum.org
- **Tipografía (CSS):** `font-family:din-pro-alternate,din-pro,sans-serif`, servida por nombre de familia y no por archivo local (probablemente Adobe Fonts, sin verificar), con iconos `design_museum_icons` — [CSS base.min.css](https://www.designmuseum.org/static/css/base.min.css).
- **Paleta (CSS):** #fff, #000, #e8e316 (amarillo), #ff00ff (magenta), #ece953.
- **Bordes y sombras (CSS):** `border-radius:100%`/`50%`/`50px`. Hay sombras de color de marca: `box-shadow:2px 0 0 rgba(232,227,22,.8)…` (resaltado amarillo tipo rotulador detrás del texto) y `0 0 5px 5px #ff00ff`.
- **Visual:** la captura no se pudo leer.
- **Identidad:** el resaltado amarillo con box-shadow imita el subrayado con rotulador, un gesto "hecho a mano" sobre una base DIN.
- **Estilos:** Editorial + Retro-modernismo institucional (DIN).

### Inferences
- **Para la UI de parqueadero (formularios de pago, tablas de tarifas, estados de cupo):** la combinación más defendible es la estructura de GOV.UK (inputs rectangulares, foco amarillo #ffdd00 como subrayado grueso, botones sólidos sin radio, tablas con reglas finas) con el tono de ETH o HfG Karlsruhe (una grotesca DIN/Theinhardt más una monoespaciada para placas, horas, montos y códigos de recibo).
- **Rasgos "humanos" reutilizables sin caer en plantilla:** las elipses de trazo fino como botones de acción principal (Rietveld y ECAL), las fechas u horas enormes como protagonistas tipográficos (HfG, que encaja con "hora de entrada/salida"), la regla horizontal como separador en lugar de tarjetas con sombra (Whitney, ETH) y el resaltado tipo rotulador (Design Museum) para el estado activo.
- Todos los sitios fuertes evitan degradados, glassmorphism y tarjetas redondeadas con sombra. Los `border-radius` no nulos que aparecen vienen casi siempre de Bootstrap heredado (Rietveld, Kunstmuseum) o son círculos deliberados (ECAL, Design Museum).
- **Clasificación por estilo:** Swiss International (GOV.UK, ETH, EPFL+ECAL Lab, Whitney, ECAL), Editorial (Whitney, ECAL, Design Museum, Art Institute of Chicago de reserva), Retro-modernismo institucional (HfG Karlsruhe, Design Museum/DIN), Minimalismo tipográfico (Yale, Kunstmuseum Basel, Rietveld, GOV.UK) y Handcrafted (Yale wiki, Rietveld).

### Gaps
- No tengo medidas exactas de tamaños y pesos de la jerarquía (px y rem por nivel), porque no extraje los valores computados. La jerarquía se describe solo cualitativamente desde las capturas.
- No hay descripción visual directa de ETH, Kunstmuseum Basel, EPFL+ECAL Lab, Design Museum ni MIT Media Lab, porque la captura falló. Conviene revisarlos manualmente antes de citarlos visualmente.
- Para los sitios con CSS de Bootstrap (Rietveld, Kunstmuseum), los hex de Bootstrap no son colores de marca.
- La fuente de EPFL+ECAL Lab es contradictoria entre Awwwards (Inter Tight) y el CSS actual (ES Klarheit Grotesk).
- El relaunch de HfG Karlsruhe anunciado para el 15.9.2026 no se comprobó. El sitio observado el 2026-11-09 mantiene el diseño descrito.
- Bauhaus Dessau: falta confirmar qué fuente usa realmente en pantalla (BundesSans Cond o Friedl).

## Pregunta 3 — Qué estilo representa cada uno y por qué (resumen para el redactor)

### Takeaway
La mayoría de las referencias son Swiss International o Minimalismo tipográfico. Para Handcrafted, Rietveld y Yale School of Art son los ejemplos más claros. Para Retro-modernismo institucional, HfG Karlsruhe es el único ejemplo verificado sólido.

### Cited Findings
- Yale School of Art: Minimalismo tipográfico + Handcrafted. Serif propia Yale New con Univers y un wiki editable — [CSS](https://art.yale.edu/_nuxt/entry.B5iVlZGs.css), [About](https://www.art.yale.edu/about-this-site).
- GOV.UK: Swiss + Minimalismo tipográfico. GDS Transport en dos pesos, radio 0 y foco amarillo — [CSS](https://www.gov.uk/assets/frontend/application-5c3385e08fe8537ee8f94db512cdc8e15439427843360d44fb88cfdac0fadfe1.css).
- ECAL: Swiss + Editorial. Renens propia, marquesina y botones ovalados — [CSS](https://ecal.ch/static/build/site/module.css?v=5nu1zamf7), [Fonts In Use](https://fontsinuse.com/typefaces/134611/renens).
- Rietveld: Handcrafted + Minimalismo. New Rail Alphabet Light y elipses de trazo — [CSS](https://rietveldacademie.nl/lib/dist/screen~166070362.css).
- Whitney: Swiss + Editorial. Neue Haas Grotesk Text/Sign y la W responsiva de Experimental Jetset — [CSS](https://whitney.org/assets/application-64097f3d.css), [identidad](https://whitney.org/newidentity).
- HfG Karlsruhe: Retro-modernismo institucional + Swiss. Theinhardt con Executive (mono) y estructura de índice — [CSS](https://www.hfg-karlsruhe.de/dist/style.css).
- ETH Zürich: Swiss. DINPro y azul #215caf — [CSS](https://ethz.ch/etc.clientlibs/ethz/design/clientlibs/css.css).
- Kunstmuseum Basel: Minimalismo tipográfico. Maison Neue en negro sobre blanco — [CSS](https://cdn.kunstmuseumbasel.ch/website/assets/20261022114657-4823/f07a77a/dist/main.css).
- EPFL+ECAL Lab: Swiss, con retícula proporcional premiada — [Awwwards](https://www.awwwards.com/sites/epfl-ecal-lab).
- Design Museum: Editorial + Retro-modernismo. DIN Pro y resaltado amarillo y magenta — [CSS](https://www.designmuseum.org/static/css/base.min.css).

### Inferences
- Para el sistema de parqueadero conviene una base Swiss/GOV.UK (claridad funcional), con un solo gesto Handcrafted o Retro (elipse de trazo, mono tipo máquina, fecha u hora gigante) para que no parezca plantilla.

### Gaps
- Las categorías "estilo" son interpretación del investigador a partir de la evidencia. No las asigna ninguna galería.
