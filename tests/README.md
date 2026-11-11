# Pruebas · UniPark

Ejecutor propio, sin Composer ni PHPUnit: basta con el PHP de XAMPP.

```bash
php tests/run.php              # todas
php tests/run.php Integration  # una carpeta
php tests/run.php Pago         # clases o métodos que contengan el texto
```

En Windows con XAMPP: `C:\xampp\php\php.exe tests\run.php`.

## Requisitos

1. MariaDB/MySQL iniciado.
2. Base de pruebas preparada una vez: `php scripts/install.php --test` (con un `.env` copiado de `.env.example`). Las pruebas recrean la base desde `schema.sql`, `base.sql` y `seed.sql`.
3. `.env` con `TEST_DB_NAME` distinto de `DB_NAME`. **La base de pruebas se borra y recrea antes de cada caso.** El ejecutor se niega a correr si ambos nombres coinciden.

Las pruebas de integración se conectan con la **cuenta de la aplicación** (`DB_USER`, sin DELETE), igual que en producción. La cuenta administrativa solo se usa para reiniciar la base y para comprobar que los disparadores bloquean incluso a un administrador.

## Organización

| Carpeta | Qué prueba | Base de datos |
|---|---|---|
| `Unit/` | Reglas puras: tarifas, montos, salida, pago, documento, contraseñas | No |
| `Integration/` | Servicios contra MariaDB real: entrada, pago, salida, auditoría, respaldo | Sí |
| `Security/` | Recorrido HTTP completo por el `Kernel`: permisos, CSRF, inyección SQL, XSS | Sí |
| `Support/` | `TestCase`, reinicio de la base y proceso auxiliar de concurrencia | — |

## Criterios de aceptación cubiertos

| ID | Escenario | Prueba |
|---|---|---|
| CP-01 | Entrada válida | `EntradaTest::testCP01EntradaValidaCreaVisitaEnCurso` |
| CP-02 | Entrada duplicada | `EntradaTest::testCP02EntradaDuplicadaSeRechazaYMuestraLaVisitaActual` |
| CP-03 | Dos entradas simultáneas | `EntradaTest::testCP03DosEntradasSimultaneasSoloConfirmanUna` (6 procesos reales) y `…AunqueSeOmitaLaAplicacion` |
| CP-04 | Pago válido | `PagoTest::testCP04PagoValidoPorElMontoExactoConConsecutivoUnico` |
| CP-05 | Pago duplicado | `PagoTest::testCP05…` (servicio, inserción directa y 5 pagos simultáneos) |
| CP-06 | Salida sin pago | `SalidaTest::testCP06SalidaSinPagoSeDeniegaYNoSeRegistra` |
| CP-07 | Salida con pago | `SalidaTest::testCP07SalidaConPagoCierraLaVisita` |
| CP-08 | Rol no autorizado por URL directa | `PermisosTest::testCP08…` |
| CP-09 | Usuario inactivo | `PermisosTest::testCP09UsuarioInactivoNoPuedeIniciarSesion` |
| CP-10 | Cambio de tarifa | `EntradaTest::testCP10…` |
| CP-11 | Inyección SQL | `SqlInjectionTest::testCP11…` |
| CP-12 | CSRF inválido | `CsrfTest::testCP12…` |
| CP-13 | Auditoría | `AuditoriaTest::testCP13…` |
| — | Tarifas por categoría | `EntradaTest::testProfesorPagaLaTarifaDeProfesor`, `…ImpideUsarLaTarifaDeOtraCategoria` |
| — | Pantallas muestran sus datos | `VistasTest` |
| CP-14 | Restauración | `BackupRestoreTest::testCP14RespaldoYRestauracionRecuperanElEstado` |

## Concurrencia (CP-03 y CP-05)

`Support/concurrente.php` es un proceso PHP independiente. La prueba lanza varios a la vez; todos esperan el mismo instante (`microtime`) y llaman al servicio real con su propia conexión. Se verifica que exactamente uno confirme y que los demás reciban el rechazo controlado (`VISITA_ABIERTA` o `PAGO_DUPLICADO`), sin errores técnicos.

## Resultado de referencia

```
147 pruebas · 0 fallidas · 0 omitidas · ~155 s (08/11/2026)
```

Windows 11, PHP 8.2.12 y MariaDB 10.4.32 (XAMPP), 05/11/2026. La salida completa está en `docs/evidencias/`.
