# Manual de usuario · Portería y Caja

## Ingreso al sistema

1. Abre UniPark y pulsa **Iniciar sesión**.
2. Escribe tu usuario y tu contraseña. Si es tu primer ingreso o te restablecieron el acceso, el sistema te pedirá **cambiar la contraseña temporal**. La nueva debe tener al menos 10 caracteres, combinar letras y números y no contener tu usuario.
3. Si escribes mal los datos, verás «Usuario o contraseña incorrectos». Después de 5 intentos fallidos el acceso se bloquea unos minutos.
4. La sesión se cierra sola tras 15 minutos sin actividad. Al terminar tu turno usa **Cerrar sesión** (abajo en el menú).
5. Las cuentas inician con la contraseña `Demo2026*`. Cámbiala cuando quieras con **Cambiar contraseña**, también abajo en el menú.

Tu cuenta es personal: todo lo que registres queda a tu nombre. No la compartas.

---

## Portería

### Registrar una entrada

1. En **Portería**, escribe el documento del estudiante o profesor y pulsa **Buscar**.
2. Confirma que el nombre corresponde a la persona. Junto al documento verás su categoría: **Estudiante** o **Profesor**.
3. Selecciona **CARRO** o **MOTO**. Cada opción muestra la tarifa de la categoría de la persona. No hay campo de placa: el sistema no la registra.

   | Vehículo | Estudiante | Profesor |
   |---|---|---|
   | Carro | $ 2.050 | $ 2.800 |
   | Moto | $ 1.050 | $ 1.800 |
4. Pulsa **Registrar entrada**. Verás la confirmación con el tipo y la tarifa aplicada.

| Mensaje | Qué significa | Qué hacer |
|---|---|---|
| «No hay un estudiante registrado con ese documento» | El estudiante no existe en el sistema | Remitirlo a Administración para su registro |
| «El estudiante está inactivo» | Su registro fue desactivado | Remitirlo a Administración |
| «El estudiante ya tiene una visita abierta» | Ya figura dentro del parqueadero | Revisa la visita que se muestra; no se crea otra |

### Autorizar una salida

1. Busca al estudiante por su documento.
2. Si tiene una visita abierta, verás la entrada, el estado del pago y el tiempo transcurrido. El tiempo es solo informativo: **no cambia el valor**.
3. Pulsa **Autorizar salida** y confirma.
   - **Con pago confirmado:** la visita queda **CERRADA** con la hora de salida y tu nombre.
   - **Sin pago:** el sistema muestra **«Pago pendiente. Diríjase a Caja.»**, no registra la salida y deja constancia del intento. Indícale al estudiante que pase por Caja y vuelve a intentarlo cuando regrese.

El panel lateral muestra cuántos carros y motos hay dentro y cuántos están pendientes de pago.

---

## Caja

### Cobrar una visita

1. En **Caja** verás las visitas abiertas sin pago. Puedes buscar por documento o nombre.
2. Pulsa **Cobrar** en la visita del estudiante.
3. Verifica el estudiante, el tipo de vehículo y el **valor a pagar**. Es la tarifa fija aplicada al entrar; no se escribe a mano.
4. Selecciona el **medio de pago**:
   - **Efectivo:** si quieres, escribe el valor recibido para ver el cambio. Ese dato no se guarda.
   - **Transferencia o datáfono:** puedes anotar la referencia o el número de autorización. **Nunca escribas números de tarjeta.**
5. Pulsa **Confirmar pago** y confirma. Se abre el **comprobante** con su consecutivo (p. ej. `R00000008`); usa **Imprimir** si el estudiante lo necesita.

El pago solo cuenta como confirmado cuando ves el comprobante. Si aparece un error, el pago **no** quedó registrado.

| Mensaje | Qué significa |
|---|---|
| «Esta visita ya tiene un pago confirmado» | Otro cajero ya la cobró, o se intentó cobrar dos veces. No se cobra de nuevo. |
| «La visita ya no está en curso» | El estudiante ya salió o la visita fue anulada |

### Reimprimir un comprobante

Desde **Cierre de caja**, pulsa el consecutivo en la lista de transacciones. Solo puedes ver los comprobantes que tú registraste.

### Cierre de caja

1. Abre **Cierre de caja**. Por defecto muestra tus pagos desde las 00:00 de hoy; ajusta el **inicio y fin del turno** si es necesario y pulsa **Actualizar**.
2. Revisa los totales por medio de pago.
3. Cuenta el efectivo, escríbelo en **Efectivo contado** y pulsa **Registrar cierre**.
4. El sistema muestra la diferencia («La caja cuadra», «Falta efectivo» o «Sobra efectivo») y registra el cierre en la auditoría.
5. Imprime el resumen si tu procedimiento lo requiere.

Si un pago se registró mal (por ejemplo, con el medio equivocado), **no se puede borrar**. Solicita su anulación al Administrador, que la registra con un motivo.
