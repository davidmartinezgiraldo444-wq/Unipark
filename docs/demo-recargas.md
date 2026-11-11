# Demostración · Recargas voluntarias y pasarela de pagos

Copia de demostración de UniPark para presentar el avance **sin tocar el sistema real**:

| | Sistema real | Demostración |
|---|---|---|
| Dirección | `http://localhost/UniPark/` | `http://localhost/UniParkDemo/` |
| Base de datos | `unipark_db` | `unipark_demo` (datos ficticios de `seed.sql`) |
| Cookie de sesión | `UNIPARK_SESSION` | `UNIPARK_DEMO` (nunca se comparten) |
| Pasarela | apagada | **simulada** (no es Wompi; no mueve dinero) |
| Rótulo | — | «MODO DEMOSTRACIÓN» en todas las pantallas |

El código es el mismo (`htdocs/UniParkDemo` solo tiene `index.php`, `.htaccess` y `config-demo.php`). El simulador se
niega a funcionar en producción y sobre `unipark_db` (`Pasarelas::exigirEntornoDePruebas`).

> Instalación, apertura, usuarios, la **ruta completa en vivo con tiempo acelerado (1 minuto de tolerancia)** y
> problemas frecuentes: ver **`docs/manual-demo.md`**. Este documento es el guion corto de la parte de pagos.

## Preparar (antes de presentar)

```
php scripts/demo.php --reiniciar
```

Deja la demo en su estado inicial: Santiago (`estudiante2`, saldo $0) entró en carro hace 20 minutos, venció la
tolerancia y tiene un **cobro pendiente de $2.000** (generado por los servicios reales de entrada y cobro).
Usuarios: los de `seed.sql` (`estudiante2`, `estudiante1`, `caja`, `admin`, `auditor`), contraseña de demostración.

## Guion (4 a 5 minutos)

1. **El problema** (30 s) — «En la portería no queremos filas para pagar. UniPark usa un saldo prepago: tú recargas
   cuando quieres y el parqueadero se descuenta solo. Nunca cobramos tu tarjeta.»
2. **Mi saldo** (30 s) — Ingresa como `estudiante2` → *Mi saldo*: saldo $0 y un cobro pendiente de $2.000.
3. **Recargar** (40 s) — *Recargar saldo* → elige $20.000 → *Continuar*. Señala: «Se creó una recarga con una
   referencia única; todavía no se movió dinero».
4. **Pagar** (40 s) — *Pagar en la pasarela* → página **SIMULADA** (franja roja). «Usa los mismos datos que Wompi:
   referencia, monto en centavos y una firma de integridad. Si alguien cambia el monto, la firma no coincide.»
5. **Aprobar** (40 s) — *Aprobar el pago* → vuelve a UniPark: recarga **Aprobada**, saldo **$18.000**.
   «El servidor recibió una notificación firmada, la verificó, consultó la transacción y acreditó $20.000; el
   cobro pendiente se pagó solo. La persona ya puede salir sin pasar por Caja.»
6. **Seguridad** (60 s) — *Panel del simulador* → *Reenviar notificación* → resultado `DUPLICADA` → *Mi saldo*
   sigue en $18.000. «Aunque la pasarela notifique dos veces, se acredita una sola vez.» Muestra el libro de
   movimientos: +$20.000 recarga, −$2.000 parqueadero.
7. **Otros resultados** (opcional) — Otra recarga → *Rechazar*: el saldo no cambia. Otra → *Dejar pendiente* →
   en el panel *Aprobar ahora*: se acredita cuando la pasarela confirma.
8. **Administración** (30 s) — Ingresa como `auditor` → *Reportes → Recargas en línea*: referencia, transacción,
   estado y la sección de conciliación.
9. **Estado y siguiente paso** (30 s) — tabla de abajo.

Qué decir si preguntan «¿esto es Wompi?»: «No. Es un simulador que imita el contrato de Wompi. Lo real del sistema es
todo lo del lado de UniPark (firmas, verificación, acreditación única, libro). El siguiente paso es conectar la cuenta
sandbox de Wompi, que usa el mismo código».

## Estado del módulo

| Hecho y probado | Siguiente paso |
|---|---|
| Saldo prepago con libro inmutable | Cuenta Wompi Sandbox real y sus llaves |
| Integración Wompi según la documentación oficial (firma de integridad verificada con el ejemplo oficial) | Confirmar la firma de eventos con un webhook real |
| Webhook firmado + consulta al proveedor + acreditación única (4 procesos simultáneos) | Conciliación diaria y reembolsos |
| Regreso desde la pasarela sin perder la sesión | Producción: HTTPS, comisiones y autorización de la universidad |
| Simulador para la demostración | — |

Evidencia: `docs/evidencias/resultado-pruebas-demo-2026-11-08.txt` (suite automática completa).
