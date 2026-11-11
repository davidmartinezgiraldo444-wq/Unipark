<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Reglas puras del punto de acceso (sin base de datos).
 * El servidor las aplica aunque el navegador ya las haya aplicado: el navegador no es de confianza.
 */
final class ReglasAcceso
{
    /**
     * Una clasificación es CONFIABLE solo si: el tipo está habilitado, la confianza alcanza el umbral y le
     * saca al menos el margen a la segunda opción. Todo lo demás es dudoso y pasa al portero.
     */
    public static function confiable(string $tipo, ?float $confianza, ?float $segunda, float $umbral, float $margen, array $habilitados): bool
    {
        if ($tipo === 'DESCONOCIDO' || !in_array($tipo, $habilitados, true) || $confianza === null) {
            return false;
        }
        if ($confianza < 0 || $confianza > 1 || ($segunda !== null && ($segunda < 0 || $segunda > 1))) {
            return false;
        }
        return $confianza >= $umbral && ($confianza - ($segunda ?? 0.0)) >= $margen;
    }

    /**
     * La salida corresponde al ingreso solo si coincide la CATEGORÍA. Sin identificación individual
     * (placa u otro), dos vehículos de la misma categoría no se pueden distinguir: esto no se oculta.
     */
    public static function coincideCategoria(string $tipoIngreso, string $tipoDetectado): bool
    {
        return $tipoIngreso === $tipoDetectado;
    }

    /** Normaliza lo que llega del navegador: cualquier valor fuera del catálogo se trata como DESCONOCIDO. */
    public static function normalizarTipo(string $tipo): string
    {
        $tipo = strtoupper(trim($tipo));
        return in_array($tipo, ReglasVisita::TIPOS, true) ? $tipo : 'DESCONOCIDO';
    }

    /** Número entre 0 y 1, o null si no es un número válido. */
    public static function probabilidad(mixed $valor): ?float
    {
        if ($valor === null || $valor === '' || !is_numeric($valor)) {
            return null;
        }
        $v = (float) $valor;
        return $v >= 0 && $v <= 1 ? $v : null;
    }
}
