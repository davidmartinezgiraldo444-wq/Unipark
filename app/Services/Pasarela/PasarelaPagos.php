<?php
declare(strict_types=1);

namespace App\Services\Pasarela;

/**
 * Contrato de una pasarela de pagos para RECARGAS VOLUNTARIAS del saldo UniPark.
 *
 * UniPark no depende de un proveedor concreto: ServicioRecargaLinea solo usa esta interfaz. Para agregar
 * otro proveedor (Mercado Pago, ePayco, PayU…) basta otra implementación y registrarla en Pasarelas.
 *
 * Reglas que toda implementación respeta:
 *  - Nunca crea cobros automáticos ni guarda datos de tarjetas o cuentas: la persona paga en la página del
 *    proveedor y autoriza cada pago.
 *  - leerNotificacion() rechaza cualquier notificación cuya autenticidad no pueda verificar.
 *  - consultar() se hace desde el servidor con credenciales privadas; el navegador nunca confirma un pago.
 */
interface PasarelaPagos
{
    /** Código corto del proveedor guardado en cada recarga (p. ej. WOMPI). */
    public function codigo(): string;

    /** SANDBOX o PRODUCCION. */
    public function entorno(): string;

    /**
     * Formulario que lleva a la persona a la página de pago del proveedor.
     * @param array{referencia:string,monto:string,expira_en:string} $recarga
     * @param array{correo:?string,nombre:string} $pagador
     * @return array{url:string,metodo:string,campos:array<string,string>}
     */
    public function formularioPago(array $recarga, array $pagador, string $urlRetorno): array;

    /**
     * Verifica la autenticidad de una notificación (webhook) y extrae la transacción reportada.
     * @param array<string,string> $cabeceras nombres en minúsculas
     * @throws PasarelaException FIRMA_INVALIDA, CUERPO_INVALIDO o ENTORNO_DISTINTO
     */
    public function leerNotificacion(string $cuerpo, array $cabeceras): NotificacionPasarela;

    /**
     * Consulta al proveedor el estado real de una transacción.
     * @return ?TransaccionPasarela null si el proveedor responde que no existe
     * @throws PasarelaException CONSULTA_FALLIDA si no se pudo obtener una respuesta confiable
     */
    public function consultar(string $idTransaccion): ?TransaccionPasarela;
}
