# Estilos de diseño web y antipatrones "genéricos / generados por IA" — aplicados a un sistema de parqueadero universitario

Nota de método: 16 búsquedas/lecturas web (nov. 2026). Las fuentes de diseño histórico consultadas son sobre todo secundarias (Wikipedia, revistas de diseño, blogs); las fuentes sobre "AI slop" son mayoritariamente blogs de agencias con interés comercial (se marcan como opinión). Las citas textuales se limitan a frases cortas obtenidas de las páginas leídas.

## 1. Origen, principios y vocabulario visual de los siete estilos

### Takeaway
Cinco de los siete estilos (suizo, técnico/industrial, retro-modernismo institucional, minimalismo tipográfico y, en parte, editorial) comparten una raíz común: el modernismo de posguerra (retícula, sans-serif neutral, sistemas documentados en manuales, función antes que ornamento). Neo-brutalismo y handcrafted son reacciones contra la homogeneidad de las plantillas. Para una app administrativa con datos densos, el núcleo suizo/técnico/institucional es el más seguro; el neo-brutalismo solo es utilizable de forma acotada.

### Cited Findings

**Swiss International Design / International Typographic Style**
- Surge en Suiza en los años 50 (algunas fuentes lo remontan a los 20–30); enfatiza limpieza, legibilidad y objetividad, maquetación asimétrica, retícula y texto alineado a la izquierda en bandera — [Wikipedia (mirror)](https://ipfs.ssi.eecc.de/ipns/en.wikipedia-on-ipfs.org/wiki/International_Typographic_Style); fechas discrepantes señaladas en la propia entrada — [Wikipedia](https://en.wikipedia.com/wiki/Swiss_Style)
- Cada diseño en este estilo parte de una retícula matemática; se buscaba comunicar "sin la interferencia del ego del diseñador" (paráfrasis de la fuente) — [PRINT Magazine](https://www.printmag.com/?p=1387)
- Josef Müller-Brockmann: carteles para la Tonhalle/Zúrich y el libro *Grid Systems in Graphic Design* (Niggli); buscaba una expresión gráfica "objetiva e impersonal"; su obra es dinámica y asimétrica aunque construida sobre retícula rígida — [Envato Tuts+](https://design.tutsplus.com/articles/what-is-swiss-design-and-why-you-should-know-its-history--cms-38463); [PRINT](https://www.printmag.com/?p=1387)
- Tipografías canónicas: Akzidenz-Grotesk y luego Helvetica y Univers (sans-serif neutras) — [PRINT](https://www.printmag.com/?p=1387); [MasterClass](https://www.masterclass.com/articles/swiss-design)

**Vignelli Canon (puente entre suizo, institucional y minimalismo tipográfico)**
- Vignelli organiza el diseño en Semántica (búsqueda de significado: "Design without semantics is shallow and meaningless"), Sintáctica (estructura, retícula, tipografías, titulares, ilustraciones; la retícula es una herramienta de consistencia sintáctica) y Pragmática (si el diseño no se entiende, es inútil) — [The Vignelli Canon (PDF, RIT)](https://www.rit.edu/vignellicenter/sites/rit.edu.vignellicenter/files/documents/The%20Vignelli%20Canon.pdf); resumen en [UX Collective](https://uxdesign.cc/the-vignelli-canon-a-design-classic-from-the-last-of-the-modernists-74d6e7dc0169)
- Defendía un repertorio tipográfico mínimo (Garamond, Bodoni, Century Expanded, Helvetica en una muestra de su obra) y sostenía que las buenas tipografías son pocas — [UX Collective](https://uxdesign.cc/the-vignelli-canon-a-design-classic-from-the-last-of-the-modernists-74d6e7dc0169); publicado por Lars Müller en 2010, PDF libre en RIT — [Substack productandrew](https://productandrew.substack.com/p/product-88)
- Aplicación a interfaces: ver [UX Collective, "The intangibles from The Vignelli Canon in interface design"](https://uxdesign.cc/the-intangibles-from-the-vignelli-canon-in-interface-design-37280cc9292c)

**Retro-modernismo institucional (manuales corporativos/institucionales 1960–70)**
- NYCTA Graphics Standards Manual (1970), Massimo Vignelli y Bob Noorda (Unimark International): reglamento de toda la señalética del metro — layouts pre-medidos de símbolos, guías de espaciado, índices con código de color, normas de redacción — [Untapped Cities](https://untappedcities.com/10-fun-facts-about-the-nyc-transit-authority-graphics-standard); [Gizmodo](https://gizmodo.com/the-legendary-manual-that-dictates-every-detail-of-the-1572251522)
- El manual especificó "Standard" (Akzidenz) porque Helvetica no estaba disponible para licencia; Helvetica se oficializó en 1989 (fechas de contratación de Unimark: 1966 o 1967, según la fuente) — [Untapped Cities](https://untappedcities.com/10-fun-facts-about-the-nyc-transit-authority-graphics-standard); [The Drum](https://www.thedrum.com/news/2019/08/20/out-the-labyrinth-how-activist-designers-created-the-visual-identity-nyc-s-subway)
- NASA Graphics Standards Manual: Richard Danne y Bruce Blackburn, diseño 1974–75, publicación oficial ~1976; carpeta de anillas 8.5×11"; cubría tipografía, color, papelería, publicaciones, señalética, vehículos, aviones y naves, todo organizado alrededor del logotipo "worm"; incluía una sección explicando qué es una retícula; NASA lo revocó en 1992; reeditado por Standards Manual — [It's Nice That](https://www.itsnicethat.com/news/nasa-graphics-standards-manual-reissue-general-release-130416); [Wired (mirror)](https://wirednews.pages.dev/2015/09/nasa-graphics-standards-manual); [Design Milk](https://design-milk.com/nasas-1975-graphics-standards-manual-reissued/); [collectSPACE](https://www.collectspace.com/news/news-090115a-nasa-logo-worm-manual.html)
- Otl Aicher: cofundó la HfG Ulm con Max Bill e Inge Scholl (1953; cerró en 1968); dirigió el grupo que rediseñó Lufthansa (1962–64); manual *Guidelines and Standards for Visual Design* de Múnich 1972 (completado ~1967), con pictogramas deportivos aún usados internacionalmente — [It's Nice That](https://www.itsnicethat.com/features/otl-aicher-design-type-thinking-graphic-design-270622); [Smithsonian](https://www.smithsonianmag.com/innovation/this-graphic-artists-olympic-pictograms-changed-urban-design-forever-180978256/); [Eye Magazine](https://eyemagazine.com/review/article/aichers-heroic-aspirations); [Metropolis](https://metropolismag.com/projects/the-olympic-feats-of-otl-aicher/) (fechas de encargo 1966 vs 1967 discrepan entre fuentes)

**Industrial / Technical Interface Design (Braun, Rams, Ulm)**
- Los 10 principios de Dieter Rams (versión Vitsœ): innovador, útil, estético, comprensible, discreto (unobtrusive), honesto, duradero, minucioso hasta el último detalle, respetuoso con el medio ambiente, "tan poco diseño como sea posible"; lema "Weniger, aber besser / less but better" (libro de 1995) — [Vitsœ](https://www.vitsoe.com/gb/about/good-design); [IxDF](https://ixdf.org/literature/article/dieter-rams-10-timeless-commandments-for-good-design)
- La HfG Ulm (Aicher, Bill) es el puente entre la escuela y la industria (Braun, Lufthansa) — [It's Nice That](https://www.itsnicethat.com/features/otl-aicher-design-type-thinking-graphic-design-270622)

**Neo-Brutalismo vs Brutalismo**
- Brutalismo web (Kate Moran, NN/g, 5 nov 2017): estilo que intencionalmente parece crudo, improvisado o sin adornos; inspirado en la arquitectura brutalista de los 50 y en webs de los 90 (Craigslist, Drudge Report); HTML casi sin estilos, enlaces azules, monoespaciadas; rechazo de los sitios de plantilla — [NN/g](https://www.nngroup.com/articles/brutalism-antidesign/)
- NN/g distingue brutalismo de *antidiseño* (feo/desorientador a propósito, sin jerarquía, colores estridentes, animaciones que distraen); recomienda limitar el brutalismo a lo visual y no alterar jerarquía, navegación o interacción — [NN/g](https://www.nngroup.com/articles/brutalism-antidesign/)
- Neo-brutalismo (Hayat Sheikh, NN/g, 11 abr 2025): evolución más colorida y ordenada; alto contraste, layouts en bloques, colores primarios vivos, bordes gruesos, sombras sólidas desplazadas (p. ej. negro ~4px), tipografías expresivas, guiños a Windows 98/monoespaciadas — [NN/g](https://www.nngroup.com/articles/neobrutalism/)
- Recomendaciones NN/g: 2–3 colores fuertes como máximo, titulares expresivos con cuerpo neutral, padding 24–32px alrededor de bordes gruesos, estados interactivos claros (subrayado/cambio de color), verificar contraste; riesgos: interfaz "abrumadora o confusa", pares como amarillo/cian fallan legibilidad — [NN/g](https://www.nngroup.com/articles/neobrutalism/)
- Opinión: otros comentaristas lo ven como una versión "productizada" y amable del brutalismo — [Bejamas](https://bejamas.com/blog/neubrutalism-web-design-trend)

**Editorial Web Design**
- Jason Santa Maria (2008) llamó a llevar dirección de arte real a la web frente a publicaciones basadas en plantillas/CMS; en su sitio diseñó "un sistema" en vez de un único look, para dirigir artísticamente cada contenido — [Zeldman](https://zeldman.com/2008/06/19/art-direction-on-the-web/); [jasonsantamaria.com v4](https://v4.jasonsantamaria.com/articles/a-new-day/)
- Autor de *On Web Typography* (A Book Apart); fue director de diseño en Slate y Vox Media — [The Great Discontent](https://thegreatdiscontent.com/interview/jason-santa-maria/); [A List Apart](https://alistapart.com/author/jsm/)

**Handcrafted Web Design y Minimalismo tipográfico**
- No se hallaron definiciones con fuente primaria específica (ver Gaps). Lo más cercano: el brutalismo como rechazo a sitios "cookie-cutter" de plantilla — [NN/g](https://www.nngroup.com/articles/brutalism-antidesign/); y el repertorio tipográfico mínimo de Vignelli como antecedente del minimalismo tipográfico — [Vignelli Canon](https://www.rit.edu/vignellicenter/sites/rit.edu.vignellicenter/files/documents/The%20Vignelli%20Canon.pdf)

### Inferences
- Tabla de vocabulario visual (síntesis del investigador a partir de lo anterior, no citada como tal):
  - Suizo: retícula modular explícita, asimetría, sans-serif neutra (Helvetica/Univers/Akzidenz o herederas), texto en bandera, color plano y escaso, fotografía objetiva, sin sombras. Fortaleza: tablas y formularios muy legibles. Riesgo: frialdad/impersonalidad.
  - Técnico/industrial: estética de instrumento (Braun): grises, un color de acento funcional (naranja/verde Braun), etiquetas pequeñas en mayúsculas, monoespaciadas para datos, filetes finos, estados de máquina visibles. Fortaleza: confianza y precisión en datos (tarifas, ocupación, saldos). Riesgo: densidad excesiva, apariencia "de backoffice".
  - Retro-modernismo institucional: manual de normas, logotipo sistemático, paleta de pocos colores codificados (como las líneas del metro), pictogramas tipo Aicher, numeración y códigos. Fortaleza: identidad institucional fuerte y coherente. Riesgo: pastiche nostálgico si se copia sin función.
  - Editorial: jerarquía tipográfica fuerte, columnas, titulares con voz, contraste serif/sans, dirección de arte por sección. Útil para portada, noticias y reglamento; poco útil dentro de pantallas transaccionales.
  - Neo-brutalismo: bordes gruesos, sombras sólidas desplazadas, colores saturados. Para un sistema de pagos, solo en dosis (p. ej. botones de acción o estados); el pleno estilo puede minar la percepción de seriedad institucional.
  - Minimalismo tipográfico: la tipografía (escala, peso, espacio) como único recurso de jerarquía; ideal para pantallas de pago donde la claridad es crítica, pero exige mucho oficio para no parecer vacío.
- El hilo común suizo–Vignelli–NASA–Aicher–Rams es "un sistema documentado con pocas reglas estrictas". Para UniPark, eso apunta a un mini "manual de normas" (tokens, retícula, códigos de color de zonas) como fuente de identidad.

### Gaps
- No se encontró una fuente autorizada que defina "Handcrafted Web Design" como movimiento con nombre propio, ni una que defina "minimalismo tipográfico" como estilo web; las descripciones dadas son inferencia.
- No se leyeron directamente *Grid Systems in Graphic Design* ni el PDF completo del Vignelli Canon; los detalles de módulos de retícula (p. ej. número de columnas recomendado) no están verificados.
- Especificaciones de la retícula del manual de la NASA no encontradas.
- No hay estudios de usabilidad específicos sobre estilos suizo/industrial en apps administrativas.

## 2. Rasgos visuales del "look genérico SaaS / generado por IA"

### Takeaway
Hay un consenso amplio (2025–2026, mayoritariamente en blogs de agencias y desarrolladores) sobre una "huella" estable: Inter/Roboto, gradiente índigo-púrpura, hero centrado con CTA, tres tarjetas redondeadas con iconos, sombras suaves, fondo blanco/gris claro, iconos de línea fina intercambiables y copy vacío. La explicación causal más citada (opinión) es la convergencia estadística hacia los defaults de Tailwind/tutoriales.

### Cited Findings
- Rasgos listados en "Why Your AI Keeps Building the Same Purple Gradient Website" (prg.sh, 26 oct 2025, autor probable Pragnesh Gajjar): acentos púrpura/índigo y gradientes púrpura sobre blanco; Inter, Roboto o Arial; hero centrado con botón; tres cajas de características con iconos en grilla; fondos blancos o gris claro; esquinas redondeadas por todas partes y sombras sutiles; paletas tímidas distribuidas uniformemente — [prg.sh](https://prg.sh/ramblings/Why-Your-AI-Keeps-Building-the-Same-Purple-Gradient-Website)
- Causa (opinión del autor): las demos de Tailwind UI usaron `bg-indigo-500` como default neutral, miles de tutoriales lo copiaron y los modelos lo aprendieron; el púrpura es "estadísticamente común en el corpus". Frase clave: "The model can execute. You have to direct." — [prg.sh](https://prg.sh/ramblings/Why-Your-AI-Keeps-Building-the-Same-Purple-Gradient-Website); misma tesis sobre Tailwind indigo-500 en [DEV Community](https://dev.to/alanwest/why-every-ai-built-website-looks-the-same-blame-tailwinds-indigo-500-3h2p)
- 925Studios (2026, blog comercial): Inter "used everywhere" como default sin decisión tipográfica; gradiente índigo-púrpura como "the single loudest AI tell in 2026"; botón con gradiente "Get Started"; hero que podría pertenecer a cualquier producto; "three rounded cards in a row" como reflejo de tutoriales de Tailwind; sombras suaves; iconos "thin-line and slightly generic"; titulares tipo "Build faster. Ship smarter." — [925Studios](https://www.925studios.co/blog/ai-slop-design-tells)
- Otras recopilaciones similares (no leídas en detalle): [Utsubo, 12 señales que bajan la confianza](https://www.utsubo.com/blog/built-with-ai-trust-signals-2026); [Noqta, AI design slop 2026](https://noqta.tn/en/blog/ai-design-slop-overused-ui-patterns-fix-2026); [Superdesign](https://superdesign.dev/blog/why-ai-design-looks-generic); [Towards AI, 10 signs](https://pub.towardsai.net/10-signs-of-ai-generated-design-and-how-to-make-yours-not-look-like-it-482ff7da81fc)
- Antecedente pre-IA: NN/g ya en 2017 describía el brutalismo como reacción a los sitios "cookie-cutter, premade-template" — [NN/g](https://www.nngroup.com/articles/brutalism-antidesign/)

### Inferences
- Lista de antipatrones a evitar en UniPark (síntesis): gradiente índigo/púrpura; Inter como única fuente sin decisión; hero centrado + 3 tarjetas con iconos; radio grande uniforme en todo; sombras difusas para separar todo; iconos de línea fina genéricos o emojis/blobs 3D; glassmorphism y animaciones gratuitas (mencionados en el encargo, pero no confirmados en las fuentes leídas); copy de eslogan vacío ("Estaciona más inteligente").
- Prohibir solo el púrpura no basta: una fuente señala que el modelo salta al siguiente default más probable — [búsqueda: resumen de varias fuentes, ver prg.sh](https://prg.sh/ramblings/Why-Your-AI-Keeps-Building-the-Same-Purple-Gradient-Website). La solución es imponer un sistema propio, no una lista negra.

### Gaps
- Glassmorphism, emojis/blobs 3D y animación gratuita como "tells" no aparecieron en las fuentes leídas en profundidad (925Studios explícitamente no los trata); no se pudo citar.
- No se encontraron ensayos de medios de referencia (AIGA Eye on Design, Figma blog, It's Nice That) sobre "AI slop" en el tiempo disponible; las fuentes son blogs comerciales/técnicos.
- No hay datos empíricos (estudios de usuarios) que demuestren que estos rasgos reducen la confianza; las afirmaciones son opinión.

## 3. Prácticas que dan identidad humana / evitar el look de plantilla

### Takeaway
Las fuentes coinciden en que la identidad viene de decisiones deliberadas y específicas del producto: tipografía con punto de vista, paleta derivada de algo verdadero del producto, romper la simetría de tres columnas, copy concreto, y trabajar con un sistema documentado (dirección de arte, manual). Los manuales históricos muestran que restricciones estrictas generan identidad, no la quitan.

### Cited Findings
- Recomendaciones 925Studios (opinión comercial): elegir tipografía con punto de vista o emparejar Inter con una distintiva; paleta construida "from something true about the product"; romper el tríptico con asimetría, otro ritmo o contenido que no encaje en tres columnas; elección deliberada de iconos; titulares que digan lo que solo tu producto hace — [925Studios](https://www.925studios.co/blog/ai-slop-design-tells)
- prg.sh: dar restricciones de diseño explícitas, referencias visuales y una lista de defaults a evitar; el humano debe aportar el criterio ("taste") — [prg.sh](https://prg.sh/ramblings/Why-Your-AI-Keeps-Building-the-Same-Purple-Gradient-Website)
- Santa Maria: diseñar un sistema que permita dirección de arte por contenido, en vez de un look único — [jasonsantamaria.com](https://v4.jasonsantamaria.com/articles/a-new-day/); [Zeldman](https://zeldman.com/2008/06/19/art-direction-on-the-web/)
- Vignelli: repertorio tipográfico reducido + retícula como consistencia sintáctica + semántica (investigar la historia del tema antes de diseñar) — [UX Collective](https://uxdesign.cc/the-vignelli-canon-a-design-classic-from-the-last-of-the-modernists-74d6e7dc0169)
- Manual NYCTA: códigos de color indexados, layouts pre-medidos y normas de redacción como parte del sistema (la voz es parte del manual) — [Untapped Cities](https://untappedcities.com/10-fun-facts-about-the-nyc-transit-authority-graphics-standard)
- Neo-brutalismo según NN/g: sombras sólidas y bordes en lugar de gradientes y sombras difusas — [NN/g](https://www.nngroup.com/articles/neobrutalism/)

### Inferences
- Para UniPark: filetes/reglas de 1px y bordes en vez de sombras; radios pequeños o nulos, coherentes; numeración sistemática visible (zonas A/B/C, niveles, puestos "P-014"); tipografía de señalética para cifras y rótulos + una sans de lectura para cuerpo; paleta derivada de la marca universitaria + colores funcionales de zona (como líneas del metro); pictogramas propios inspirados en el sistema Aicher (auto, moto, bicicleta, discapacidad, entrada/salida); copy concreto y con voz institucional ("Tu saldo cubre 3 días más en la zona B").
- Restricción deliberada (pocos colores, una retícula, dos tipografías) es la lección común de NASA/NYCTA/Vignelli/Rams.

### Gaps
- No se encontró evidencia empírica de que fotografía real o ilustración personalizada aumente la confianza en apps administrativas; se recomienda, pero sin fuente en esta búsqueda.

## 4. Relevancia de la señalética / wayfinding para un sistema de parqueadero

### Takeaway
La señalética de transporte es la referencia más natural y defendible para la identidad de un parqueadero: sus tipografías (DIN 1451, Transport) y sistemas (NYCTA, Schiphol, Aicher) se diseñaron precisamente para legibilidad rápida, códigos de color y pictogramas, que son las mismas necesidades de una interfaz de ocupación, acceso y pago.

### Cited Findings
- DIN 1451: estándar publicado por el instituto DIN en 1931 (norma oficial 1936); origen en el rotulado de la Administración de Ferrocarriles Reales de Prusia; obligatorio en la Autobahn desde 1938; revisado en 1981 ("Autobahn-Schrift"), versión PostScript en 1990, última revisión 2018 — [Wikipedia DIN 1451](https://en.wikipedia.org/wiki/DIN_1451); versión contemporánea comercial: [Fontwerk Neue DIN](https://www.fontwerk.com/fonts/neue-din)
- Transport (Jock Kinneir y Margaret Calvert, 1957–63) para los comités Anderson y Worboys; probado en el bypass de Preston (1958) y luego la M1; uso de mayúsculas/minúsculas para leer nombres a velocidad; pruebas en lluvia, noche y velocidad; "l" con curva para distinguirla de "I" y "1" (inspirada en Johnston); aún es una de las dos únicas tipografías permitidas en señales viales del Reino Unido; influyó en Clearview (EE. UU.); versión digital New Transport con Henrik Kubel (2009 o 2012 según la fuente) — [Wikipedia Transport](https://en.wikipedia.org/wiki/Transport_(typeface)); [Thames & Hudson](https://thamesandhudson.com/blogs/all-news-features/designing-the-transport-typeface-margaret-calvert-on-the-making-of-britain-s-road-signs); [The Beauty of Transport](https://thebeautyoftransport.com/2014/12/03/sign-languages-transport-and-ministry-typefaces-uk)
- Schiphol: Mijksenaar rediseñó la señalética en los 90; paneles amarillos saturados con texto negro para direcciones; legado ISOTYPE (diseño para todos) — [Wim Crouwel Instituut](https://wimcrouwelinstituut.nl/en/research/designing-for-everyone); la tipografía (citada como Frutiger) proviene de una fuente de baja calidad — [madegooddesigns](https://madegooddesigns.com/schiphol-airport-font/) (no verificado)
- Pictogramas de Aicher (Múnich 72) como origen de gran parte de la señalética pública moderna — [Smithsonian](https://www.smithsonianmag.com/innovation/this-graphic-artists-olympic-pictograms-changed-urban-design-forever-180978256/)
- NYCTA: el manual unificó toda la señalética del metro con código de color por línea — [Gizmodo](https://gizmodo.com/the-legendary-manual-that-dictates-every-detail-of-the-1572251522)

### Inferences
- Una tipografía de tipo DIN (o alternativa libre de la misma familia) para rótulos, números de puesto, placas y cifras de tarifa conecta directamente con la señal vial que el usuario ve en el parqueadero físico; esto da identidad sin recurrir a decoración.
- El panel amarillo/negro de Schiphol y el código de color de líneas NYCTA sugieren: un color de alto contraste reservado para orientación/acción y colores por zona de parqueo, con contraste verificado (WCAG).
- Coherencia física-digital: si la universidad tiene o crea señalética en el parqueadero, la interfaz debe usar los mismos códigos (letras de zona, pictogramas, colores).

### Gaps
- No se confirmaron licencias/alternativas libres concretas de DIN o Transport para web (p. ej. qué fuentes de Google Fonts son derivadas); requiere verificación aparte.
- No se encontraron casos documentados de interfaces web de parqueaderos que usen señalética vial como identidad.
- No se verificó la tipografía exacta de Schiphol con fuente primaria (Mijksenaar).
