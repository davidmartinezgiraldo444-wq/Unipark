<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Reglas puras de visitas y pagos, sin base de datos, para poder probarlas de forma aislada.
 * Los servicios las aplican dentro de transacciones con filas bloqueadas.
 */
final class ReglasVisita
{
    public const TIPOS = ['CARRO', 'MOTO'];
    public const CATEGORIAS = ['ESTUDIANTE', 'PROFESOR'];

    public const MENSAJES = [
        'SIN_VISITA'        => 'No existe la visita indicada.',
        'VISITA_NO_ABIERTA' => 'La visita ya está cerrada o anulada.',
        'PAGO_PENDIENTE'    => 'Pago pendiente. Diríjase a Caja.',
        'PAGO_OTRA_VISITA'  => 'El pago no corresponde a esta visita.',
        'PAGO_DUPLICADO'    => 'Esta visita ya tiene un pago confirmado.',
        'MONTO_DISTINTO'    => 'El monto no coincide con la tarifa aplicada a la visita.',
    ];

    public static function tipoValido(string $tipo): bool
    {
        return in_array($tipo, self::TIPOS, true);
    }

    public static function categoriaValida(string $categoria): bool
    {
        return in_array($categoria, self::CATEGORIAS, true);
    }

    /**
     * RN-06 y RN-08: la salida solo se autoriza si la visita está abierta
     * y tiene un pago confirmado para esa misma visita.
     * @return ?string código de rechazo, o null si la salida procede
     */
    public static function evaluarSalida(?array $visita, ?array $pago): ?string
    {
        if ($visita === null) {
            return 'SIN_VISITA';
        }
        if ($visita['estado'] !== 'EN_CURSO') {
            return 'VISITA_NO_ABIERTA';
        }
        if ($pago === null || $pago['estado'] !== 'PAGADO') {
            return 'PAGO_PENDIENTE';
        }
        if ((int) $pago['id_visita'] !== (int) $visita['id_visita']) {
            return 'PAGO_OTRA_VISITA';
        }
        return null;
    }

    /**
     * RN-05, RN-09 y sección 8.4: un único pago válido, por el monto exacto de la tarifa aplicada.
     * @return ?string código de rechazo, o null si el pago procede
     */
    public static function evaluarPago(?array $visita, ?array $pagoExistente, string $monto): ?string
    {
        if ($visita === null) {
            return 'SIN_VISITA';
        }
        if ($visita['estado'] !== 'EN_CURSO') {
            return 'VISITA_NO_ABIERTA';
        }
        if ($pagoExistente !== null && $pagoExistente['estado'] === 'PAGADO') {
            return 'PAGO_DUPLICADO';
        }
        if (!self::montoCoincide($monto, (string) $visita['tarifa_aplicada'])) {
            return 'MONTO_DISTINTO';
        }
        return null;
    }

    /** Compara en centavos para evitar errores de coma flotante. */
    public static function montoCoincide(string $monto, string $tarifa): bool
    {
        return self::centavos($monto) === self::centavos($tarifa);
    }

    public static function centavos(string $valor): int
    {
        [$entero, $decimal] = array_pad(explode('.', trim($valor), 2), 2, '0');
        return (int) $entero * 100 + (int) str_pad(substr($decimal, 0, 2), 2, '0');
    }

    public static function mensaje(string $codigo): string
    {
        return self::MENSAJES[$codigo] ?? 'La operación no está permitida.';
    }
}
