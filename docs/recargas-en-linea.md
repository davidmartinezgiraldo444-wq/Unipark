# Recargas en línea del saldo prepago (Wompi)

UniPark funciona con un **saldo prepago interno**. La persona **inicia y autoriza cada recarga**; el parqueadero se
descuenta solo de ese saldo. **UniPark nunca debita tarjetas ni cuentas bancarias**: no guarda medios de pago, no
crea cobros recurrentes y la pasarela solo procesa pagos que la persona hace en su página.

Estado: implementado y probado con un **Wompi simulado** (pruebas automáticas sin internet). **No se ha probado
contra una cuenta sandbox real de Wompi** ni con dinero real. No está listo para producción hasta completar la
sección «De sandbox a producción».

## 1. Flujo

```
Mi saldo → Recargar → elige valor → POST /mi-saldo/recargar
   → ServicioRecargaLinea::crear()        recarga CREADA (referencia UPK-…, vence en 60 min). No mueve dinero.
   → /mi-saldo/recargas/{id}              botón «Pagar» → formulario GET a https://checkout.wompi.co/p/
        (public-key, amount-in-cents, reference, signature:integrity, expiration-time, redirect-url)
   → la persona paga en Wompi con PSE, tarjeta, Nequi, Bancolombia… (lo que tenga habilitado el comercio)
   → Wompi → POST /api/pasarela/wompi/eventos  (webhook)
        1. firma: SHA-256(valores de signature.properties + timestamp + secreto de eventos) = X-Event-Checksum
        2. entorno del evento = entorno configurado (test / prod)
        3. consulta GET /v1/transactions/{id} con la llave privada: el estado que cuenta es el de Wompi
        4. ServicioRecargaLinea::aplicarTransaccion(): referencia + monto + moneda deben coincidir
        5. APROBADA → movimiento RECARGA (medio PASARELA) + saldo + recarga APROBADA, en una transacción
                      → el saldo nuevo paga los cobros pendientes (ServicioCobro::aplicarSaldoAPendientes)
           PENDIENTE / RECHAZADA / ANULADA / ERROR → solo cambia el estado; no hay dinero
   → la persona vuelve a /pago/retorno?ref=UPK-…&id=…   (página puente SIN sesión: no envía Set-Cookie)
        anota el id (nunca acredita) y redirige desde el mismo sitio a /mi-saldo/recargas/{id}?volvio=1,
        donde la cookie de sesión SameSite=Strict sí viaja → la página SOLO muestra el estado
        (si la sesión venció durante el pago: login → regreso a la recarga, solo rutas internas)
cron cada 5 min → scripts/conciliar-recargas.php → consulta a Wompi las recargas sin confirmar y vence las no pagadas
```

### Cobro de parqueadero (sin cambios de reglas)

- Tolerancia de 15 minutos, tarifas y tipos de vehículo: **sin cambios**.
- Al vencer la tolerancia, `ServicioCobro` descuenta del saldo. Si no alcanza, el cobro queda **PENDIENTE**, el saldo
  **nunca queda negativo** y la persona recibe un aviso con acceso directo a «Mi saldo → Recargar».
- La **salida no cambió**: solo consulta el estado del cobro. No llama a la pasarela ni cobra.

**Diferencia entre estado financiero y salida:** con un cobro PENDIENTE la salida sigue denegada («Pago pendiente.
Diríjase a Caja.»). Ahora la persona puede resolverlo desde el celular antes de llegar a la portería: recarga en línea
→ la acreditación paga el cobro → la salida se autoriza. Si recarga en el carril, la confirmación de la pasarela tarda
desde segundos (tarjeta, Nequi) hasta minutos (PSE): por eso el aviso llega **al vencer la tolerancia**, no en la
salida. Propuesta pendiente de aprobación: mostrar en el panel de Portería un contador de «salidas en espera de pago»
para orientar a esas personas a un lado del carril, sin bloquearlo.

## 2. Pasarela recomendada: Wompi

| Criterio | Wompi |
|---|---|
| Disponibilidad | Colombia (Grupo Bancolombia) |
| Medios | PSE, tarjetas débito/crédito, Nequi, Botón y QR Bancolombia, Daviplata, corresponsales, entre otros (según la cuenta) |
| Integración PHP | Web Checkout por formulario GET + webhook JSON + API REST: sin SDK ni dependencias |
| Seguridad | Firma de integridad del pago, firma SHA-256 de eventos, consulta con llave privada, sandbox separado |
| Datos de tarjeta | Nunca pasan por UniPark (checkout alojado por Wompi) |
| Documentación | https://docs.wompi.co/docs/colombia/ (consultada el 08/11/2026) |
| Comisiones | **No verificadas**: la página de tarifas no se pudo consultar. Confírmalas con Wompi antes de contratar |

Alternativas evaluadas y no integradas: Mercado Pago, ePayco y PayU (también ofrecen PSE y tarjetas). La capa
`App\Services\Pasarela\PasarelaPagos` permite agregar otra implementación sin tocar cobros, Caja ni portería.

## 3. Configurar la cuenta de Wompi (sandbox)

1. La universidad (persona jurídica) crea la cuenta de comercio en Wompi. La aprobación para producción exige
   documentos de la empresa; **sandbox se puede usar mientras tanto**.
2. En el panel de comercios → **Desarrolladores**, copia las cuatro llaves del **ambiente de pruebas**:
   `pub_test_…`, `prv_test_…`, secreto de eventos `test_events_…` y secreto de integridad `test_integrity_…`.
3. En **URL de eventos** (sandbox) registra: `https://<tu-dominio>/<ruta>/api/pasarela/wompi/eventos`.
   Debe ser HTTPS público. En XAMPP local usa un túnel (ngrok, Cloudflare Tunnel) que apunte a Apache.
4. Configura las variables de la sección 4 en `.env` o en las variables del servidor (no en el repositorio).
5. Aplica las migraciones (sección 5) y programa el cron de conciliación.
6. Prueba con los datos de sandbox de Wompi: tarjeta `4242 4242 4242 4242` (aprobada), `4111 1111 1111 1111`
   (rechazada); Nequi `3991111111` (aprobada), `3992222222` (rechazada); PSE: «Banco que aprueba» / «Banco que rechaza».
7. Verifica en **Reportes → Recargas en línea** que cada pago aparece con su transacción y que la sección
   «Conciliación» no muestra `FIRMA_INVALIDA`.

## 4. Variables de entorno

| Variable | Ejemplo | Uso |
|---|---|---|
| `PASARELA_PROVEEDOR` | `WOMPI` | Vacío = recargas en línea apagadas |
| `WOMPI_ENTORNO` | `sandbox` | `sandbox` o `produccion` |
| `WOMPI_PRODUCCION_AUTORIZADA` | `0` | Debe ser `1` para aceptar pagos reales |
| `WOMPI_LLAVE_PUBLICA` | `pub_test_…` | Va al formulario de pago (es pública) |
| `WOMPI_LLAVE_PRIVADA` | `prv_test_…` | Solo servidor: consulta de transacciones |
| `WOMPI_SECRETO_EVENTOS` | `test_events_…` | Solo servidor: verifica los webhooks |
| `WOMPI_SECRETO_INTEGRIDAD` | `test_integrity_…` | Solo servidor: firma cada pago |
| `APP_URL` | `https://dominio/UniPark` | Retorno desde la página de pago |
| `RECARGA_LINEA_VALORES` | `10000,20000,30000,50000,100000` | Botones de valor |
| `RECARGA_LINEA_MINIMA` / `_MAXIMA` | `10000` / `500000` | Límites por recarga |
| `RECARGA_LINEA_EXPIRA_MIN` | `60` | Minutos para pagar |
| `PASARELA_VERIFICAR_CONSULTA` | `1` | Consultar a Wompi antes de acreditar (recomendado) |

Las llaves deben coincidir con el entorno: en sandbox se rechazan llaves `*_prod_` y al revés.

## 5. Base de datos

Migraciones (aplicar con `php scripts/migrar.php`, que saca respaldo y otorga privilegios mínimos):

- `20261108_1000_debito_unico_por_pago.sql` — corrige E1: un DEBITO por **pago** (antes por cobro).
- `20261108_1010_recargas_en_linea.sql` — medio `PASARELA`, tablas `recargas_en_linea` y `notificaciones_pasarela`,
  disparadores de inmutabilidad y regla de recarga sin cajero.

Garantías: `recargas_en_linea.id_movimiento` es UNIQUE (una acreditación por recarga); una recarga APROBADA no se
modifica; `notificaciones_pasarela` solo admite INSERT; el libro `movimientos_saldo` sigue sin UPDATE ni DELETE.
Las correcciones se hacen con movimientos compensatorios (reverso de recarga con motivo, en Administración).

Cron:

```
0-59/5 * * * * php /ruta/UniPark/scripts/conciliar-recargas.php
```

## 6. De sandbox a producción

1. Cuenta de comercio aprobada por Wompi y comisiones confirmadas por escrito.
2. Sitio en HTTPS con certificado válido y `APP_URL` correcta.
3. URL de eventos de **producción** registrada en el panel (es distinta de la de sandbox).
4. Llaves `pub_prod_`, `prv_prod_`, `prod_events_`, `prod_integrity_` en el servidor; `WOMPI_ENTORNO=produccion`.
5. Autorización expresa de la institución → `WOMPI_PRODUCCION_AUTORIZADA=1`.
6. Una recarga real de valor mínimo, verificada de punta a punta (pago, webhook, saldo, reporte) y conciliada con el
   panel de Wompi.
7. Procedimiento escrito para reembolsos y para los casos `REQUIERE_REVISION` de la conciliación.

## 7. Regreso desde la pasarela y cookie de sesión (B1, corregido el 08/11/2026)

La cookie de sesión es `SameSite=Strict`: en una navegación que llega desde otro sitio (checkout.wompi.co → UniPark)
el navegador no la envía. Antes, la página de destino iniciaba una sesión nueva y su `Set-Cookie` **reemplazaba** la
sesión real: la persona quedaba desconectada. Comprobado en un navegador real (contraprueba).

Corrección, sin relajar la cookie:

- Rutas con `'sesion' => false` en `app/routes.php` (`/pago/retorno` y el webhook): `index.php` no llama a
  `Session::start()` (`Kernel::sinSesion()`), así que no emiten `Set-Cookie`.
- `/pago/retorno` no muestra datos de la persona ni de la recarga; anota el id reportado usando la referencia
  aleatoria (solo la conoce quien creó la recarga) y redirige con `<meta http-equiv="refresh">`: esa navegación la
  inicia una página del propio sitio, así que la cookie viaja.
- Si ya se conoce una transacción reportada, el detalle de la recarga deja de ofrecer «Pagar» (evita el doble pago).
- Login con `?volver=`: solo rutas internas en minúsculas (`volver_seguro()`); se ignoran `//sitio`, `https:`,
  consultas, `login`, `logout` y `api/`.

## 8. Pendiente de verificar

- Webhook y consulta contra el sandbox real de Wompi (se probaron con un Wompi simulado).
- El ejemplo de checksum de la página de eventos de Wompi no coincide con su propia cadena de ejemplo; la fórmula
  implementada es la documentada (y la firma de integridad sí reproduce el ejemplo oficial). Confirmar con el primer
  webhook real del sandbox.
- Comisiones y límites por transacción del proveedor.
