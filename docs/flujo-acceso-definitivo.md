# Flujo definitivo de acceso · persona → vehículo → estacionamiento

Versión: 08/11/2026 · Complementa `ENTREGA_ACTUALIZACION.md`.

## 1. Comparación con el diseño anterior (auditoría del 08/11/2026)

| Tema | Antes | Diseño definitivo |
|---|---|---|
| Quién elige el tipo de vehículo | El celular del escáner mostraba 4 botones manuales | El punto de acceso **no** tiene botones. Solo la IA o el **portero** (desde su panel) definen el tipo |
| Detección dudosa | Se quedaba en la pantalla del escáner | Se crea un **evento pendiente** que aparece **solo** en el panel de Portería |
| Salida | Solo se consultaba el pago | Primero se identifica a la persona, después se **verifica el vehículo** contra el ingreso activo y por último se consulta el pago |
| Relación persona‑vehículo | Implícita (la visita) | **Evento de acceso** explícito: persona, puesto, detección, confianza, método, portero, visita |
| Tarifa y tiempo | En el servidor (ya correcto) | Sin cambios: el servidor calcula todo desde la base |
| Cuenta del celular de acceso | Cuenta de Portería (podía registrar entradas manuales) | Rol **Punto de acceso** (dispositivo): no puede elegir el tipo ni resolver eventos |

## 2. Entidades

```
estudiantes (persona)
   │ 1
   ├──< eventos_acceso  (ENTRADA | SALIDA)  ── puesto, detección IA, confianza, método, portero
   │        │ 0..1
   └──< visitas (estacionamiento) ── tolerancia_hasta, liquidación, cobro
                 │
                 └──< cobros_diarios ──< pagos / movimientos_saldo
```

**Evento de acceso** (`eventos_acceso`): una pasada por la portería.

| Estado | Significado |
|---|---|
| `EN_CAPTURA` | La identidad está validada y la cámara está clasificando el vehículo |
| `PENDIENTE_PORTERO` | La IA no fue confiable, el vehículo no coincide o la identidad no está registrada: espera al portero |
| `CONFIRMADO` | Entrada registrada, o salida verificada (autorizada o denegada por pago) |
| `RECHAZADO` | Rechazado por el portero, o persona no autorizada |
| `EXPIRADO` | Nadie lo resolvió a tiempo (cron). No se registró nada |

## 3. Entrada

```
Punto de acceso (celular)                 Servidor                                   Panel de Portería
1 escanea carné/cédula ──► POST /api/acceso/identificar
                           · persona activa y sin visita abierta → crea evento EN_CAPTURA
                           · inactiva → evento RECHAZADO (motivo) ──► «No autorizado»
                           · no registrada → evento PENDIENTE_PORTERO (IDENTIDAD_NO_REGISTRADA) ──► aviso
2 IA clasifica (navegador) ─► POST /api/acceso/{id}/clasificacion {tipo, confianza, segunda}
                           · el servidor exige: rol Punto de acceso o Portería, evento EN_CAPTURA,
                             tipo habilitado, confianza ≥ umbral y margen ≥ margen (config del servidor)
                           · confiable → ServicioEntrada::registrar (tarifa y tolerancia del servidor)
                           · no confiable → PENDIENTE_PORTERO (VEHICULO_NO_CLASIFICADO) ──────────► aviso
3 consulta el estado ◄──── GET /api/acceso/{id}                           ◄── portero elige el tipo
                                                                            POST /api/porteria/eventos/{id}/resolver
```

## 4. Salida

```
1 escanea carné ──► identificar: hay visita abierta → evento SALIDA EN_CAPTURA (con id_visita)
2 IA clasifica ───► clasificacion:
      · confiable e IGUAL al tipo del ingreso → ServicioSalida::autorizar (solo consulta el pago)
      · confiable y DISTINTO → PENDIENTE_PORTERO (VEHICULO_NO_COINCIDE) — no se cierra
      · no confiable / sin detección → PENDIENTE_PORTERO (VEHICULO_NO_CLASIFICADO) — no se cierra
3 portero: elige el tipo real → si coincide, se consulta el pago; si no coincide, RECHAZADO y la visita sigue abierta
```

La salida **nunca** cobra: solo consulta el estado del cobro diario (regla vigente).

## 5. Temporizador

`visitas.tolerancia_hasta = entrada_en + 15 min`, calculado **en la base de datos**. El cron
(`scripts/cobros.php`, cada minuto) liquida las visitas vencidas. El navegador no envía tiempos,
tarifas ni importes. Si el tipo de vehículo está pendiente, **no existe visita**, así que no corre ninguna
tolerancia ni se cobra con una categoría arbitraria.

## 6. Concurrencia y relación persona‑vehículo (caso E)

- Un punto de acceso tiene **un solo evento en captura** a la vez (índice único `captura_puesto`).
  Si se escanea otra persona, la captura anterior se expira (queda auditada).
- Una persona tiene **un solo evento abierto** (índice único `evento_abierto_persona`).
- En el modo de dos celulares, el servidor solo acepta una detección del puesto **posterior** al escaneo
  (`detectado_en ≥ evento.creado_en`) y con menos de 8 s de antigüedad. Así se evita usar el vehículo anterior.
- **Limitación real:** UniPark clasifica la **categoría** del vehículo; no lo identifica individualmente.
  Dos automóviles distintos son indistinguibles. La coincidencia de salida compara la categoría. Si el carril
  tiene varios vehículos a la vez o la cámara ve uno distinto, la detección no es confiable y decide el portero.
  No se afirma una precisión que la cámara de un celular no ofrece.

## 7. Identificación individual futura (placas u otro)

`app/Services/IdentificadorVehiculo.php` define la interfaz. La implementación actual
(`SinIdentificadorVehiculo`) devuelve «no disponible». Un lector de placas o etiquetas (por ejemplo RFID)
se conectaría ahí, sin mezclarse con la clasificación de categoría. **Decisión pendiente:** la política
inicial prohíbe guardar placas; habilitarlo requiere aprobación y una evaluación de privacidad.

## 8. Decisiones de negocio pendientes

1. **Patineta (scooter):** la especificación nueva contempla solo automóvil, motocicleta y bicicleta.
   `PATINETA` sigue en la base (no se elimina), pero queda **deshabilitada** en `config/app.php`
   (`tipos_habilitados`). Reactivarla es un cambio de configuración.
2. **Visitantes:** antes, cualquier documento no registrado entraba como visitante. Ahora el punto de acceso
   no los admite solo: el portero decide (admitir como visitante, con su vehículo, o rechazar).
3. **Expiración de eventos sin atender:** 10 minutos (`eventos_expiran_min`).
4. **Captura de imagen para el portero:** desactivada por defecto (`ia.captura_portero = false`), por la política
   de no guardar fotografías. Si se activa, la imagen se guarda temporalmente y se borra al resolver el evento.
