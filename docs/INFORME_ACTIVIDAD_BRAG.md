# Informe de la actividad · Agente de video con IA

**Actividad:** Crear un video explicativo de un proyecto de GitHub
**Proyecto:** UniPark · Sistema de control de acceso y operación del parqueadero universitario
**Asignatura:** Bases de Datos · 2026
**Autor del proyecto:** David Martínez Giraldo · **Docente:** Gabriel Barrera
**Repositorio:** <https://github.com/davidmartinezgiraldo444-wq/Uni-Park>
**Fecha:** 06/11/2026

| Entregable | Ubicación |
|---|---|
| Video final (MP4, 1920×1080, 30 s) | [`brag-output/brag.mp4`](../brag-output/brag.mp4) |
| Análisis del repositorio | [`docs/ANALISIS_UNIPARK_BRAG.md`](ANALISIS_UNIPARK_BRAG.md) |
| Plan de producción | [`brag-output/brag-plan.md`](../brag-output/brag-plan.md) |
| Guion audiovisual | [`brag-output/guion.md`](../brag-output/guion.md) |
| Subtítulos | [`brag-output/subtitulos.srt`](../brag-output/subtitulos.srt) |
| Texto de presentación | [`brag-output/presentacion-video.md`](../brag-output/presentacion-video.md) |
| Índice de entregables y validación | [`brag-output/README.md`](../brag-output/README.md) |

---

## 1. Objetivo

Demostrar que un agente de inteligencia artificial puede analizar un repositorio de software, entender su estructura y sus funcionalidades, y convertir esa información en una presentación audiovisual que sea técnicamente correcta.

## 2. Herramientas de IA utilizadas

| Herramienta | Uso |
|---|---|
| **Claude Code** (aplicación de escritorio, modelo **Claude Opus 5.5**) | Agente que leyó el código, redactó los documentos, escribió el código de la animación y el audio, y ejecutó el render |
| **Brag** (plugin de Claude Code, v0.5.0, <https://github.com/latent-spaces/brag>) | Skill que convierte un proyecto en un video de lanzamiento corto. Con Opus 5.5, `/brag` delega en **`/brag-slim`**, la variante en la que el modelo construye todo el video con las herramientas disponibles en el equipo |

### Cómo funciona `/brag-slim`

La skill define un flujo de cuatro fases y un conjunto de "leyes creativas":

1. **Inspeccionar**: leer el código, los estilos (colores y fuentes exactos), el README y los componentes, y responder qué es el producto, para quién es, qué lo diferencia y qué interfaz real se puede mostrar.
2. **Planear**: escribir `brag-plan.md` con el ángulo, el gancho, los momentos destacados, el cierre y un guion gráfico cuyos cortes caen en el pulso de la música.
3. **Construir, revisar y renderizar**: revisar fotogramas fijos de cada escena y de las transiciones antes del render final.
4. **Entregar**: elegir la mejor miniatura, incrustarla como fotograma 0 y escribir el texto para publicar.

Leyes aplicadas: duración corta, claridad para alguien que no conoce el proyecto, mostrar la interfaz real en vez de recrearla, usar los textos propios del proyecto, textos legibles (unos 0,3 s por palabra) y nada de datos sensibles en pantalla.

**Adaptación académica:** la skill original produce videos de 15–25 s sin narración. Para cumplir la actividad se amplió a 30 s, con narración en español, tono académico y un documento de análisis previo.

## 3. Proceso seguido

| Fase | Qué hizo el agente | Resultado |
|---|---|---|
| 1. Identificación | Revisó la carpeta, `composer.json`, la estructura, la documentación y la evidencia de pruebas. Comprobó que la copia local no tiene Git; con el enlace dado por el autor, verificó en el navegador que el repositorio existe (estaba vacío) | §1 del análisis |
| 2. Análisis | Leyó `routes.php`, `permissions.php`, `schema.sql`, los servicios (`ReglasVisita`, `ServicioEntrada`, `ServicioPago`, `ServicioSalida`), las vistas, la documentación y el resultado de las pruebas. Buscó en el código cada funcionalidad pedida (por ejemplo «recarga», «saldo», «placa») | `docs/ANALISIS_UNIPARK_BRAG.md` |
| 3. Documentación | Clasificó cada funcionalidad como implementada, parcial o pendiente, con evidencia en archivos concretos | §8 y §11 del análisis |
| 4. Planificación | Escribió el plan de producción y el guion a partir de las seis partes pedidas: problema, solución, funcionalidades, roles, tecnologías y valor | `brag-plan.md`, `guion.md` |
| 5. Generación | Sintetizó la narración, compuso la música, programó la animación con las capturas reales y renderizó | `brag.mp4` |
| 6. Entregables | Organizó los archivos, los subtítulos, el texto de presentación y los recursos | `brag-output/` |
| 7. Validación | Decodificó el MP4 completo, midió el audio y revisó fotogramas y sincronía | `brag-output/README.md` |

## 4. Resultados del análisis (resumen)

- **Qué es:** una aplicación web que obliga a seguir el orden **entrada → pago → salida**. Sin pago confirmado, el sistema responde «Pago pendiente. Diríjase a Caja.» y no registra la salida.
- **Arquitectura:** monolito PHP en capas (Kernel → Middleware → Controllers → Services → Repositories → MySQL), sin frameworks. Las reglas críticas se aplican en PHP y también en la base de datos (índices únicos, claves foráneas compuestas y disparadores).
- **Roles reales:** Portería, Caja, Administrador y Auditor. Estudiantes y profesores son personas registradas, **no** usuarios del sistema.
- **No existen en el código:** recargas o saldo prepagado, registro de placas (excluido por diseño), pagos en línea ni un rol Estudiante. El video no muestra ninguna de ellas.
- **Calidad:** 78 pruebas automatizadas, 0 fallidas (`docs/evidencias/resultado-pruebas.txt`).

Detalle completo: [`ANALISIS_UNIPARK_BRAG.md`](ANALISIS_UNIPARK_BRAG.md).

## 5. Generación del video

### Estructura (30 s, cortes sobre una rejilla de 120 BPM)

| Escena | Tiempo | Contenido |
|---|---|---|
| 1. El problema | 0,0–4,5 s | «¿Quién entra? ¿Quién paga? ¿Quién sale?» |
| 2. La solución | 4,5–7,5 s | Marca UniPark y el titular real «Entrada, pago y salida. En ese orden.» |
| 3. El flujo | 7,5–14,0 s | Capturas reales de Portería, Caja, salida denegada y salida autorizada, con un cursor simulado |
| 4. Control | 14,0–19,0 s | Capturas reales de Tarifas y Auditoría |
| 5. Roles y tecnología | 19,0–24,5 s | Los cuatro roles, PHP 8, MySQL/MariaDB, HTML·CSS·JS y 78 pruebas |
| 6. Cierre | 24,5–30,0 s | «UniPark: tecnología al servicio de la movilidad universitaria.» |

### Técnica

| Componente | Cómo se produjo |
|---|---|
| Imagen | Página HTML/CSS ([`video.html`](../brag-output/work/video.html)) con la paleta y la tipografía reales de UniPark (`assets/css/styles.css`, IBM Plex). Cada fotograma es una función del tiempo; Google Chrome sin interfaz (vía `puppeteer-core`) captura los 900 fotogramas |
| Capturas | Seis imágenes reales de `docs/evidencias/`; los nombres que aparecen son datos de demostración ficticios (`database/seed.sql`) |
| Narración | Voz local de Windows *Microsoft Laura* (síntesis en el equipo, sin servicios externos), recortada y ubicada escena por escena |
| Música y efectos | Generados por código ([`audio.mjs`](../brag-output/work/audio.mjs)): 120 BPM en Fa mayor, efectos en la misma tonalidad y atenuación automática bajo la voz |
| Codificación | `ffmpeg-static`: H.264 (CRF 17) + AAC 192 kb/s, 1920×1080 a 30 fps |

### Control de calidad

Se revisaron fotogramas de cada escena y de mitad de las transiciones. Esa revisión encontró y corrigió dos errores: el logotipo «P» no se veía en la escena 2 y la barra de direcciones mostraba una ruta POST en lugar de la página real. Al final se decodificó el MP4 completo sin errores y se midió el audio (medio −18,1 dB, pico −1,0 dB).

## 6. Tecnologías

| Ámbito | Tecnologías |
|---|---|
| UniPark | PHP ≥ 8.1 (sin dependencias), MySQL/MariaDB InnoDB, HTML, CSS, JavaScript, XAMPP |
| Producción del video | Claude Code + Brag (`/brag-slim`), Node.js 24, `puppeteer-core` 23.11.1, Google Chrome, `ffmpeg-static` 5.2.0, voces OneCore de Windows (PowerShell) |

## 7. Limitaciones

1. **Acento de la narración:** la voz disponible en el equipo es española, no latinoamericana. Se eligió así para no enviar el guion a un servicio externo.
2. **Repositorio vacío:** el 06/11/2026 el repositorio de GitHub aún no tenía archivos. El análisis se hizo sobre la copia local, que es la que el autor subirá.
3. **Enlace fuera del video:** el enlace al repositorio aparece en los documentos, no dentro del video, para no volver a renderizarlo.
4. **Dependencias del render:** `brag-output/work/node_modules/` (114 MB) no se sube a GitHub (`brag-output/.gitignore`); se reinstala con `npm install`.

## 8. Conclusión

El agente identificó el proyecto y verificó sus funcionalidades directamente en el código, distinguiendo las implementadas de las inexistentes. Con eso produjo un video de 30 s en 1920×1080, con narración, música, capturas reales y textos fieles al sistema. Todo el proceso es reproducible con los scripts de `brag-output/work/`. Ningún archivo de UniPark se modificó ni se borró, y la base de datos no se tocó.
