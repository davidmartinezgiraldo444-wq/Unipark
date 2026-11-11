<?php
declare(strict_types=1);

namespace App\Services\Pasarela;

use App\Helpers\ClienteHttpCurl;

/**
 * Elige la pasarela configurada (config recargas_en_linea.proveedor). Hoy solo existe WOMPI;
 * otro proveedor se agrega con un caso más en crear().
 */
final class Pasarelas
{
    private static ?PasarelaPagos $forzada = null;

    /** Las pruebas inyectan una pasarela con un cliente HTTP falso (nunca salen a internet). */
    public static function usar(?PasarelaPagos $pasarela): void
    {
        self::$forzada = $pasarela;
    }

    /** @throws PasarelaException NO_CONFIGURADA o CONFIGURACION */
    public static function activa(): PasarelaPagos
    {
        if (self::$forzada !== null) {
            return self::$forzada;
        }
        return self::crear((string) (config('recargas_en_linea')['proveedor'] ?? ''));
    }

    /** Verdadero si hay una pasarela configurada y su configuración es válida. */
    public static function disponible(): bool
    {
        try {
            self::activa();
            return true;
        } catch (PasarelaException) {
            return false;
        }
    }

    /** @throws PasarelaException */
    public static function crear(string $proveedor): PasarelaPagos
    {
        switch ($proveedor) {
            case 'WOMPI':
                $wompi = new PasarelaWompi((array) config('wompi'), new ClienteHttpCurl());
                if ($wompi->entorno() === 'SANDBOX') {
                    self::exigirEntornoDePruebas('Wompi sandbox');
                }
                return $wompi;
            case 'SIMULADO':
                self::exigirEntornoDePruebas('El simulador de pagos');
                return new PasarelaSimulada((array) config('simulador'), url('simulador-pago'));
            case '':
                throw new PasarelaException('NO_CONFIGURADA', 'Las recargas en línea no están habilitadas.');
            default:
                throw new PasarelaException('NO_CONFIGURADA', 'Proveedor de pagos no soportado: ' . mb_substr($proveedor, 0, 20) . '.');
        }
    }

    /**
     * B2: el dinero de pruebas (sandbox o simulador) nunca entra al libro de la base real. Se exige una instancia de
     * pruebas (APP_ENV distinto de produccion) y una base distinta de la protegida (unipark_db).
     * @throws PasarelaException CONFIGURACION
     */
    public static function exigirEntornoDePruebas(string $quien, ?string $entorno = null, ?string $base = null): void
    {
        $entorno ??= (string) config('entorno');
        $base ??= (string) \App\Helpers\Env::get('DB_NAME', '');
        if ($entorno === 'produccion') {
            throw new PasarelaException('CONFIGURACION', $quien . ' no se usa en una instalación de producción: monta la copia de demostración (scripts/demo.php).');
        }
        if ($base === (string) config('base_protegida')) {
            throw new PasarelaException('CONFIGURACION', $quien . ' no puede acreditar saldo de pruebas en la base protegida ' . config('base_protegida') . '.');
        }
    }
}
