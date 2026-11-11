<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Reglas puras del cobro diario, sin base de datos, para probarlas de forma aislada.
 *
 *  1. Toda visita tiene 15 minutos de tolerancia desde la entrada: si sale antes, no se cobra.
 *  2. Al vencer la tolerancia, se cobra solo si la persona tiene categoría con tarifa
 *     (estudiante, profesor o trabajador) y el vehículo tiene tarifa (carro o moto).
 *  3. Se cobra una sola vez por persona y por día: si ya existe el cobro del día, la visita queda cubierta.
 *  4. La salida solo consulta el resultado: autoriza si no corresponde cobro o si el cobro está pagado.
 */
final class ReglasCobro
{
    public const MENSAJES = [
        'PAGO_PENDIENTE' => 'Pago pendiente. Diríjase a Caja.',
    ];

    /** Fin de la tolerancia para una entrada (Y-m-d H:i:s). */
    public static function finTolerancia(string $entrada, int $minutos): string
    {
        return (new \DateTimeImmutable($entrada))->modify("+{$minutos} minutes")->format('Y-m-d H:i:s');
    }

    /** La tolerancia sigue corriendo mientras ahora < tolerancia_hasta. A los 15:00 exactos ya vence. */
    public static function enTolerancia(?string $toleranciaHasta, string $ahora): bool
    {
        return $toleranciaHasta !== null && $ahora < $toleranciaHasta;
    }

    /**
     * Liquidación inicial al registrar la entrada: 'NO_APLICA' | 'CUBIERTA' | 'EN_TOLERANCIA'.
     * $totalDia: suma de los cobros vigentes de la persona ese día (null si aún no tiene).
     * Si ya pagó hoy una tarifa igual o mayor, la visita queda cubierta. Si la tarifa de este vehículo es
     * mayor, la visita espera su tolerancia: al vencer se cobra solo la diferencia.
     */
    public static function liquidacionInicial(string $categoria, string $tipo, ?string $totalDia, ?string $tarifa = null): string
    {
        if (!self::generaCobro($categoria, $tipo)) {
            return 'NO_APLICA';
        }
        if ($totalDia === null) {
            return 'EN_TOLERANCIA';
        }
        return $tarifa !== null && self::diferencia($tarifa, $totalDia) !== null ? 'EN_TOLERANCIA' : 'CUBIERTA';
    }

    /**
     * Cuánto falta para llegar a esta tarifa con lo ya cobrado hoy (regla de la tarifa más alta del día).
     * @return ?string null si lo cobrado ya cubre esta tarifa
     */
    public static function diferencia(string $tarifa, string $totalDia): ?string
    {
        $centavos = ReglasVisita::centavos($tarifa) - ReglasVisita::centavos($totalDia);
        return $centavos > 0 ? number_format($centavos / 100, 2, '.', '') : null;
    }

    /** Solo personas registradas con categoría tarifable, en carro o moto. */
    public static function generaCobro(string $categoria, string $tipo): bool
    {
        return in_array($categoria, ReglasVisita::CATEGORIAS, true) && ReglasVisita::tipoTarifable($tipo);
    }

    /**
     * Qué hacer con una visita cuando vence su tolerancia.
     * El total del día es la tarifa más alta de los vehículos usados: primer cobro completo, y si después
     * usa un vehículo de tarifa mayor, solo la diferencia.
     * @param ?string $totalDia suma de los cobros vigentes del día (null si no hay)
     * @return 'COBRAR'|'DIFERENCIA'|'CUBIERTA'|'NO_APLICA'
     */
    public static function alVencer(array $visita, ?string $totalDia): string
    {
        if (!self::generaCobro($visita['categoria'], $visita['tipo_vehiculo']) || $visita['id_tarifa'] === null) {
            return 'NO_APLICA';
        }
        if ($totalDia === null) {
            return 'COBRAR';
        }
        return self::diferencia((string) $visita['tarifa_aplicada'], $totalDia) !== null ? 'DIFERENCIA' : 'CUBIERTA';
    }

    /** El saldo alcanza para el valor del cobro (comparación en centavos). */
    public static function saldoSuficiente(string $saldo, string $valor): bool
    {
        return ReglasVisita::centavos($saldo) >= ReglasVisita::centavos($valor);
    }

    /**
     * Decisión de salida de una visita del esquema diario. No calcula tarifas ni genera cobros.
     * @return ?string null si se autoriza; 'VENCIDA_SIN_LIQUIDAR' si la tolerancia venció y aún no se
     *                 procesó (el llamador procesa esa visita y vuelve a consultar); u otro código de rechazo.
     */
    /**
     * Estadías de varios días: si el vehículo sigue dentro cuando empieza un día nuevo, ese día se cobra
     * (desde las 00:00, sin nueva tolerancia: la persona ya estaba dentro). Devuelve los días, desde el siguiente
     * a la entrada hasta hoy, que todavía no tienen un cobro de esta visita.
     * @param list<string> $diasConCobro fechas (Y-m-d) que ya tienen un cobro de la visita, incluso anulado
     * @return list<string>
     */
    public static function diasDeEstadiaPorCobrar(string $fechaEntrada, string $hoy, array $diasConCobro): array
    {
        $dias = [];
        $d = (new \DateTimeImmutable(substr($fechaEntrada, 0, 10)))->modify('+1 day');
        $fin = new \DateTimeImmutable(substr($hoy, 0, 10));
        for ($i = 0; $d <= $fin && $i < 366; $i++, $d = $d->modify('+1 day')) {
            if (!in_array($d->format('Y-m-d'), $diasConCobro, true)) {
                $dias[] = $d->format('Y-m-d');
            }
        }
        return $dias;
    }

    /** Liquidaciones de una visita que generan (o usan) un cobro y por tanto también pagan los días siguientes. */
    public const LIQUIDACIONES_CON_COBRO = ['COBRADA', 'DIFERENCIA', 'CUBIERTA'];

    public static function evaluarSalida(?array $visita, ?array $cobro, string $ahora): ?string
    {
        if ($visita === null) {
            return 'SIN_VISITA';
        }
        if ($visita['estado'] !== 'EN_CURSO') {
            return 'VISITA_NO_ABIERTA';
        }
        switch ($visita['liquidacion']) {
            case 'NO_APLICA':
            case 'SALIDA_EN_TOLERANCIA':
                return null;
            case 'EN_TOLERANCIA':
                return self::enTolerancia($visita['tolerancia_hasta'], $ahora) ? null : 'VENCIDA_SIN_LIQUIDAR';
            case 'COBRADA':
            case 'DIFERENCIA':
            case 'CUBIERTA':
                // Un cobro anulado ya no se debe; un cobro pagado autoriza la salida.
                if ($cobro !== null && in_array($cobro['estado'], ['PAGADO', 'ANULADO'], true)) {
                    return null;
                }
                return 'PAGO_PENDIENTE';
        }
        return 'PAGO_PENDIENTE';
    }
}
