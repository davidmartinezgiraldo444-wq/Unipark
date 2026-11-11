# Patrones de interfaz y sistemas de diseño con identidad para UIs densas (parqueadero universitario UniPark)

Fecha de verificación de todas las fuentes: 2026-11-08/09. Las fuentes marcadas como "secundaria" no son documentación oficial.

## 1. ¿Qué sistemas de diseño públicos tienen carácter institucional/técnico y buena documentación? ¿Cómo tratan tablas, formularios, botones, errores, estados, foco?

### Takeaway
GOV.UK, USWDS e IBM Carbon son los tres sistemas mejor documentados y verificados para UIs operativas densas: dan reglas concretas (alineación numérica, densidades de fila, foco amarillo/negro, tono de errores, indicadores de estado con forma+color+texto). Gov.br, GOV.CO (Kit UI) y la Confederación Suiza existen y son oficiales, pero su documentación pública es más difícil de consultar programáticamente; Uber Base, SBB, TfL, Bloomberg y Atlassian no se pudieron verificar en detalle.

### Cited Findings

**GOV.UK Design System (Reino Unido)**
- Tablas: las columnas numéricas que se comparan se alinean a la derecha (`govuk-table__header--numeric` / `govuk-table__cell--numeric`, o `format: "numeric"` en Nunjucks) — [GOV.UK Table](https://design-system.service.gov.uk/components/table/)
- Tablas: usar `<caption>` como si fuera un encabezado; `scope="col"`/`scope="row"` en celdas de cabecera; contenido alineado arriba por defecto; modificador `govuk-table--small-text-until-tablet` para tablas con muchos datos en móvil (Frontend ≥ 5.2); nunca usar tablas para maquetar — [GOV.UK Table](https://design-system.service.gov.uk/components/table/)
- Mensajes de error: mostrar junto al campo y en un "error summary" arriba; mismo texto en ambos; texto en rojo tras la pregunta y la pista, borde rojo que conecta mensaje y campo; prefijo oculto "Error:" para lectores de pantalla; no borrar los campos del formulario — [GOV.UK Error message](https://design-system.service.gov.uk/components/error-message/)
- Tono de errores: lenguaje llano y positivo; decir qué pasó y cómo arreglarlo; instrucción para campos vacíos ("Enter your first name") y descripción para entradas incorrectas ("Name must be 35 characters or less"); evitar "please", "sorry", "valid/invalid", "oops", "forbidden/illegal", "you forgot" y mensajes genéricos tipo "An error occurred" o "This field is required" — [GOV.UK Error message](https://design-system.service.gov.uk/components/error-message/)
- No usar el mensaje de error para problemas de elegibilidad, permisos o capacidad del servicio: eso va en una página aparte — [GOV.UK Error message](https://design-system.service.gov.uk/components/error-message/)
- Estado de foco: combinación amarillo + negro con borde inferior grueso, visible sobre cualquier fondo; enlaces enfocados = fondo amarillo + borde inferior negro; inputs = contorno amarillo + borde negro engrosado; mismo estilo en radios y checkboxes — [GOV.UK Focus states](https://design-system.service.gov.uk/get-started/focus-states/)

**U.S. Web Design System (USWDS)**
- Variantes de tabla: estándar con bordes (`usa-table`), rayada/zebra (`usa-table--striped`), sin bordes (`usa-table--borderless`), compacta (`usa-table--compact`), con scroll, apilada en móvil (cada celda con `data-label`), ordenable (`data-sort-value` + `aria-sort`) y cabecera fija (sticky) — [USWDS Table](https://designsystem.digital.gov/components/table/)
- En los ejemplos de código, las celdas numéricas usan las utilidades `font-mono-sm text-tabular text-right` (monoespaciada, cifras tabulares, alineación derecha) — [USWDS Table](https://designsystem.digital.gov/components/table/) (la explicación textual de esa guía no se pudo leer completa)

**IBM Carbon**
- Tabla de datos con 5 alturas de fila: XS 24px, S 32px, M 40px (por defecto), L 48px, XL 64px (solo para filas de dos líneas); la cabecera debe igualar la altura de fila — [Carbon Data table usage](https://carbondesignsystem.com/components/data-table/usage/)
- Toolbar de tabla para acciones globales (filtros, exportar, ajustes) con hasta 5 acciones; el resto en menú overflow; barra de acciones por lote aparece al seleccionar filas y desactiva las acciones por fila; zebra opcional "para facilitar el escaneo horizontal" — [Carbon Data table usage](https://carbondesignsystem.com/components/data-table/usage/)
- Patrón de indicadores de estado: tipos icono (icono+forma+color+etiqueta), forma (forma+color+etiqueta, para listas densas), badge (contador sobre icono, máx. 3 dígitos) y diferencial (+/−, flecha o caret; verde positivo, rojo negativo) — [Carbon Status indicators](https://carbondesignsystem.com/patterns/status-indicator-pattern/)
- Accesibilidad de estados: el color solo no basta; usar al menos dos de color/forma/símbolo, y para WCAG al menos tres de cuatro (símbolo, forma, color, texto); contraste no textual ≥ 3:1; los indicadores de forma en amarillo/naranja claro necesitan contorno — [Carbon Status indicators](https://carbondesignsystem.com/patterns/status-indicator-pattern/)
- Semántica de color de estado: rojo = peligro/error, naranja = advertencia seria, amarillo = advertencia, verde = normal/éxito, azul = notificaciones pasivas/progreso, gris = borradores/no iniciado, morado = atípico/indefinido — [Carbon Status indicators](https://carbondesignsystem.com/patterns/status-indicator-pattern/)
- Tipografía: IBM Plex (Sans, Serif, Mono); estilos "productive" (`-01`) condensados para producto y foco en tareas vs "expressive" (`-02`) para editorial; pesos Light/Regular/SemiBold; texto neutro, azul solo para enlaces y acciones primarias — [Carbon Typography](https://carbondesignsystem.com/elements/typography/overview/)

**Gov.br (Brasil) — Padrão Digital de Governo**
- El Design System está en gov.br/ds; se presentó la versión 4.0 (Design System 4.0) por el MGI — [Serpro: Design System 4.0](https://www.serpro.gov.br/menu/noticias/noticias-2024/design-system-4.0)
- Tipografía institucional: familia Rawline; Verdana como alternativa web cuando Rawline no está disponible (según manual de marca) — [Manual de marca gov.br](https://www.gov.br/governodigital/pt-br/legislacao/gov-br/govbr-manualmarca.pdf)
- El propio guía de estilo advierte que, por su carácter dinámico, parte del contenido puede estar desactualizado — [Guia de estilo gov.br](https://www.gov.br/gestaodeconteudo/pt-br/arquivos/guia-de-estilo)

**GOV.CO (Colombia) — Kit UI (verificado que existe)**
- El sistema de diseño de GOV.CO se llama Kit UI, lo administra la Agencia Nacional de Gobierno Digital; componentes agrupados en transversales, generales y de formulario; incluye paleta, tipografías, botones, iconos y formularios; basado en diseño atómico; versión más reciente encontrada 9.2 (agosto 2025) — [MinTIC artículo 406089](https://www.mintic.gov.co/portal/715/w3-article-406089.html); [Kit UI 8.1 PDF](https://das997q1qk8hr.cloudfront.net/uploads/2023-12-26/5a3dbff4-5f9a-4914-858c-6d295097e6ae-KIT%20UI%20V.%208.1%20PDF.pdf)
- Uso obligatorio para sedes electrónicas según Resolución 2893 de 2020 y sus anexos; implementación vía CDN de GOV.CO; biblioteca en gov.co/biblioteca — [Anexo 2.1 Res. 2893/2020](https://gobiernodigital.mintic.gov.co/692/articles-161266_Anexo_2_1_Resolucion_2893_2020.pdf); [Biblioteca GOV.CO](https://www.gov.co/biblioteca/)
- GOV.CO renovó su imagen para fortalecer la identidad digital del Estado — [DPL News](https://dplnews.com/colombia-gobierno-nacional-renueva-imagen-de-gov-co-para-fortalecer-la-identidad-digital-del-estado/)

**Confederación Suiza**
- Sistema de diseño vigente en swiss.github.io/designsystem (Storybook con assets, componentes y layouts); el antiguo styleguide está marcado como versión vieja; código MIT en GitHub — [GitHub swiss/designsystem](https://github.com/swiss/designsystem); [opensource.admin.ch](https://www.opensource.admin.ch/en/softwares/aab3tf)
- Oblique es el ecosistema frontend unificado (Angular) de la Confederación — [opensource.admin.ch](https://www.opensource.admin.ch/softwares/2odubf)

**Canada.ca**
- Las especificaciones obligatorias (quién debe adoptar, elementos requeridos, principios, plantillas y patrones) están en design.canada.ca/specifications — [Canada.ca design](https://design.canada.ca/); [Specifications](https://design.canada.ca/specifications.html)

**Uber Base**
- Base Web es la librería React open source de Uber con un punto de entrada para tematizar todos los tokens (color, tamaño, tipografía) — [Uber Engineering: Introducing Base Web](https://eng.uber.com/introducing-base-web)
- Uber usa su tipografía propia Uber Move (cortes Display, Text, Mono), no licenciada públicamente — [superdesign.dev](https://www.superdesign.dev/blog/uber-design-system) (secundaria)

### Inferences
- Para UniPark (Colombia), el Kit UI de GOV.CO da legitimidad institucional local, pero los patrones más detallados y reutilizables para tablas densas y estados vienen de Carbon (densidades, toolbar, estados) y GOV.UK/USWDS (alineación numérica, foco, errores).
- Tratamiento concreto reutilizable: tabla con reglas finas horizontales (no tarjetas), fila 32–40px en administración y 48px+ en portería, números en monoespaciada/tabulares a la derecha, foco amarillo/negro de alto contraste estilo GOV.UK, mensajes de error en tono instructivo ("Ingrese la placa del vehículo") sin "por favor/lo sentimos/inválido".
- Evitar mezclar demasiados sistemas: elegir uno base (p. ej. lógica de Carbon) y tomar reglas puntuales de los otros.

### Gaps
- No pude leer en detalle la documentación de gov.br/ds (la página no devolvió contenido), ni confirmar botones "pill" ni tokens actuales de Rawline.
- No verifiqué el contenido del Kit UI 9.2 (tipografía exacta, radios, colores) más allá de su existencia.
- No verifiqué radios, sombras ni tipografía del sistema suizo, ni las fuentes (Lato/Noto Sans) de Canada.ca/GC Design System.
- SBB digital (digital.sbb.ch) rechazó la descarga (HTTP 418); TfL, Bloomberg Terminal y Atlassian no se investigaron por límite de tiempo: no hay hallazgos verificados sobre ellos.
- La página de tipografía de GOV.UK (GDS Transport, cifras tabulares) devolvió 404 en la URL probada.

## 2. Ejemplos de dashboards operativos / control de parqueo / tránsito / pagos que se sientan diseñados por humanos

### Takeaway
Hay muy pocos casos de estudio públicos y de calidad sobre pantallas de operador de portería de parqueaderos; el más útil es el caso Toogethr (gate manager), que muestra la importancia de consolidar en una sola vista la búsqueda por placa y la información del usuario. La mayoría del resto es marketing de proveedores.

### Cited Findings
- Caso Toogethr (gestor de portería, 2023): en pruebas de usabilidad el operador tenía que cambiar entre dos pestañas; el rediseño consolidó todo en un solo lugar, con el campo de búsqueda de placa pre-llenado pero editable, y mejor visibilidad de la información del usuario; la UI anterior no manejaba problemas de suscripción — [Ilona Khomenko portfolio](https://ilonaart.github.io/portfolio/toogethr.html)
- Parking BOXX/CloudEASE: muestra cada estación de pago, barrera, cámara y kiosco en una pantalla y ofrece vista en vivo de lo que ve el conductor en la máquina para soporte remoto — [Parking BOXX](https://parkingboxx.com/parking-management-systems) (marketing de proveedor)
- Hikvision: las entradas/salidas combinan cámara ANPR (reconocimiento de placas), barrera, radar anti-caída y pantalla LED — [Hikvision brochure](https://pro-av.hikvision.com/content/dam/hikvision/en/brochures-download/product-brochures/Brochure_Parking-Management-Solutions_20251031.pdf)
- Módulo de parqueo Duall Master: énfasis en revisión de excepciones por el operador y acciones manuales con registro de auditoría — [Duall Master](https://duall-master-website.pages.dev/modules/parking/)
- Mobisoft: actividad de barreras de varias sedes en una pantalla con seguimiento de patrones de entrada/salida — [Mobisoft](https://mobisoftinfotech.com/solutions/parking-management-software) (marketing)

### Inferences
- Pantalla de portería de UniPark: una sola vista con (1) campo de placa grande, pre-llenado por reconocimiento pero editable, (2) resultado del usuario/vehículo con estado claro, (3) acción principal enorme (Registrar entrada/salida), (4) excepciones (placa no reconocida, sin pago, fuera de tolerancia) como casos explícitos, y (5) toda acción manual registrada en el historial (auditoría).

### Gaps
- No encontré colecciones verificadas en Mobbin, Awwwards, SiteInspire o Land-book específicas de parqueo/control; no se citan galerías por no haberlas verificado.
- No se verificaron interfaces de terminal estilo Bloomberg ni salas de control de tránsito con fuentes primarias.

## 3. Buenas prácticas: tablas, dinero, temporizadores, badges de estado, objetivos táctiles, tono de mensajes, gráficos sin decoración, login con fondo y desenfoque

### Takeaway
Las reglas verificadas son: números tabulares alineados a la derecha; zebra o líneas finas (ambos válidos); primera columna como identificador legible (placa, no ID); cabeceras fijas; estados con al menos color+forma+texto y contraste no textual 3:1; objetivos táctiles de al menos 24×24 px (AA) y preferiblemente 44×44 px; gráficos con máxima proporción tinta-datos; texto sobre imagen con overlay/desenfoque y contraste ≥ 4.5:1.

### Cited Findings
**Tablas**
- Congelar filas y columnas de cabecera cuando la tabla excede la pantalla; la primera columna debe ser un identificador legible, no un ID autogenerado — [NN/g Data tables](https://www.nngroup.com/articles/data-tables/)
- Bordes ligeros, zebra y resaltado al pasar el cursor son todos válidos para no perder la fila al escanear (NN/g no elige uno) — [NN/g Data tables](https://www.nngroup.com/articles/data-tables/)
- Columnas importantes primero y relacionadas juntas; ocultar/reordenar/ordenar fácil de descubrir; indicar claramente cuando hay filtros activos o columnas ocultas — [NN/g Data tables](https://www.nngroup.com/articles/data-tables/)
- Alinear a la derecha columnas numéricas que se comparan — [GOV.UK Table](https://design-system.service.gov.uk/components/table/)
- CSS: `font-variant-numeric: tabular-nums` da cifras de igual ancho para alinear columnas (OpenType `tnum`); `lining-nums` cifras sobre la línea base (`lnum`); `slashed-zero` distingue el 0 de la O (`zero`) — [MDN font-variant-numeric](https://developer.mozilla.org/en-US/docs/Web/CSS/font-variant-numeric)

**Estados (daltonismo)**
- Usar al menos dos de color/forma/símbolo, idealmente tres de cuatro incluyendo texto; contraste 3:1 — [Carbon Status indicators](https://carbondesignsystem.com/patterns/status-indicator-pattern/)

**Objetivos táctiles (portería en móvil)**
- WCAG 2.2 SC 2.5.8 (AA): mínimo 24×24 px CSS; SC 2.5.5 (Enhanced): 44×44 px CSS — [W3C Understanding Target Size](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum.html)

**Tono de mensajes**
- Ver reglas de GOV.UK en la sección 1 (instrucciones directas, sin "por favor/lo sentimos/inválido/oops") — [GOV.UK Error message](https://design-system.service.gov.uk/components/error-message/)

**Gráficos sin decoración**
- Tufte (The Visual Display of Quantitative Information, 1983): proporción tinta-datos = tinta usada para datos / tinta total; maximizarla dentro de lo razonable borrando tinta no-dato y redundante; "chartjunk" = decoración que no dice nada nuevo — [Wikipedia: Chartjunk](https://en.wikipedia.org/wiki/Chartjunk); [data.europa.eu guía](https://data.europa.eu/apps/data-visualisation-guide/chart-junk-and-data-ink-origins)
- Matiz: Few consideró la definición de chartjunk de Tufte "demasiado laxa", y algún estudio encontró que el chartjunk mejoró levemente el recuerdo a largo plazo; la proporción es una herramienta, no una meta — [Wikipedia: Chartjunk](https://en.wikipedia.org/wiki/Chartjunk)
- NN/g tiene un artículo sobre reducir el desorden en gráficos — [NN/g Clutter charts](https://www.nngroup.com/articles/clutter-charts/) (no leído en detalle)

**Login con ilustración de fondo + desenfoque**
- Contraste mínimo 4.5:1 texto normal y 3:1 texto grande; oscurecer con overlay/gradiente semitransparente (incluso radial); subir opacidad de la caja tras el texto (REI pasó de 30% a 50% para aprobar); desenfocar la zona tras el texto para uniformar el fondo; diseñar para el peor caso (imagen más clara); verificar con un analizador de contraste — [NN/g Text over images](https://www.nngroup.com/articles/text-over-images/)

### Inferences
- Dinero (COP): usar cifras tabulares y alinear a la derecha; mostrar el símbolo/unidad de forma consistente (p. ej. "$ 12.500" con punto de miles, sin decimales para pesos). Recomendación basada en las reglas de alineación anteriores, no en una fuente específica de formato monetario.
- Temporizador de tolerancia (minutos): mostrar en monoespaciada/tabular para que los dígitos no "bailen", con texto + color + forma al cambiar de estado (p. ej. "En tolerancia · 08:42" → "Tolerancia vencida"), siguiendo la regla de Carbon de no depender solo del color.
- Portería: botones principales muy por encima de 44×44 px (p. ej. altura completa de pulgar, ancho completo), filas de tabla de 48px+ (Carbon L/XL).
- Login: formulario sobre un panel opaco o con desenfoque fuerte (backdrop-filter) y overlay oscuro, verificando 4.5:1 con la imagen más clara posible.

### Gaps
- No encontré fuente verificada específica sobre formato de moneda en UIs (p. ej. guía de GOV.UK o Intl.NumberFormat) dentro del presupuesto de búsqueda.
- No hay fuente verificada sobre diseño de cuentas regresivas/temporizadores ni sobre "estilo suizo" de gráficos específicamente.
- No se verificó soporte de `backdrop-filter` ni guías WCAG específicas para desenfoque.

## 4. Qué evitar en dashboards (críticas citadas)

### Takeaway
Stephen Few es la crítica de referencia: los dashboards fallan por exceder una pantalla, dar poco contexto, exceso de precisión, medidas deficientes, gráficos inapropiados y variedad sin sentido; los medidores/velocímetros típicos deberían "desterrarse" y sustituirse por bullet graphs. No encontré una cita primaria de Few específica contra los donuts.

### Cited Findings
- Errores de Few (parte 1 de 13): (1) exceder una sola pantalla, (2) contexto insuficiente para los datos, (3) exceso de detalle o precisión, (4) medida deficiente (sin definición/unidad clara), (5) medio de visualización inapropiado, (6) variedad sin sentido (mezclar tipos de gráfico por evitar monotonía) — [The Data School, parte 1](https://www.thedataschool.co.uk/anh-vu/are-you-making-these-13-dashboard-design-mistakes/); [parte 2](https://www.thedataschool.co.uk/anh-vu/are-you-making-these-13-dashboard-design-mistakes-part-2)
- Few: el problema no es el concepto de medidor sino su diseño; "la mayoría de los medidores disponibles en productos de dashboard deberían ser desterrados"; creó los bullet graphs para reemplazar medidores y diales — [Perceptual Edge blog](https://www.perceptualedge.com/blog/?p=1423)
- Few, ensayo "Why Most Dashboards Fail": critica dashboards llenos de medidores y gráficos llamativos que dicen poco — [Few, Why Most Dashboards Fail (PDF)](https://blogs.ischool.berkeley.edu/i247s13/files/2013/02/WhyMostDashboardsFail.pdf)
- Un archivo de terceros atribuye a los principios de Few evitar pie, donut, radar, 3D, velocímetros realistas y embudos en dashboards — [GitHub d7mtg/dashboard-design](https://raw.githubusercontent.com/d7mtg/dashboard-design/main/SKILL.md) (secundaria, no es cita de Few)

### Inferences
- Para el panel de estadísticas de UniPark: evitar la rejilla de tarjetas KPI con iconos y degradados; preferir una tabla-resumen o una fila de cifras con contexto (comparación vs. día/semana anterior, con +/− y flecha como el indicador diferencial de Carbon), barras horizontales y líneas de tiempo simples en vez de donuts y medidores; mantener todo en una pantalla y no mezclar tipos de gráfico sin motivo.
- Ocupación del parqueadero: un bullet graph o barra simple con capacidad como referencia es más legible que un velocímetro.

### Gaps
- No verifiqué críticas específicas sobre "sidebar con iconos emoji" o la "estética genérica de SaaS/IA"; no hay fuente citable recolectada.
- Los errores 7–13 de Few no se leyeron en detalle (solo está enlazada la parte 2).
