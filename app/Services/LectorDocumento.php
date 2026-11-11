<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Interpreta el texto leído por la cámara (código de barras del carnet o de la cédula).
 * Regla pura, sin base de datos.
 *
 *  - Carnet universitario: el código es un identificador corto (documento o código propio del carnet).
 *    El formato real del carnet debe confirmarse con una muestra; ver docs/integracion-camaras.md.
 *  - Cédula colombiana amarilla con holograma: PDF417 al respaldo. El número está en las posiciones
 *    48 a 57 (con ceros a la izquierda) y los apellidos y nombres en bloques de 23 caracteres.
 *    De la cédula solo se usan el número y el nombre: el resto (fecha de nacimiento, RH, sexo,
 *    huella) se descarta en este punto y nunca se guarda.
 *  - Cédula digital: zona MRZ "ICCOL<número>"; si no se reconoce, se toma la secuencia numérica más
 *    larga de 6 a 10 dígitos y el portero puede corregirla antes de continuar.
 */
final class LectorDocumento
{
    /**
     * Texto PDF417 de PRUEBA con la misma estructura posicional que interpretar() reconoce (número en las posiciones
     * 48-57; apellidos y nombres en bloques de 23). Lleva un encabezado propio «UNIPARK-FICTICIO»: sirve para
     * demostrar la lectura de PDF417 con datos ficticios, sin reproducir el código de ningún documento real.
     */
    public static function cedulaDePrueba(string $numero, string $apellidos, string $nombres): string
    {
        $ascii = static fn (string $t): string => strtoupper(trim((string) preg_replace('/[^A-Za-z ]/', '', strtr($t,
            ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u',
             'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N', 'Ü' => 'U']))));
        $a = array_pad(explode(' ', $ascii($apellidos), 2), 2, '');
        $n = array_pad(explode(' ', $ascii($nombres), 2), 2, '');
        return str_pad('UNIPARK-FICTICIO', 48, ' ') . str_pad($numero, 10, '0', STR_PAD_LEFT)
            . str_pad($a[0], 23) . str_pad($a[1], 23) . str_pad($n[0], 23) . str_pad($n[1], 23)
            . 'DATOS-DE-PRUEBA' . str_repeat(' ', 20);
    }

    /** @return array{codigo:string,nombre:string,formato:string} */
    public static function interpretar(string $texto): array
    {
        $limpio = trim(str_replace("\0", ' ', $texto));
        $compacto = strtoupper(preg_replace('/[\s.]/', '', $limpio));

        if (preg_match('/^[0-9A-Z-]{5,20}$/', $compacto) === 1) {
            return ['codigo' => $compacto, 'nombre' => '', 'formato' => 'CARNET'];
        }

        // PDF417 de la cédula amarilla: carga larga con el número en una posición fija.
        if (strlen($limpio) >= 150) {
            $numero = ltrim(substr($limpio, 48, 10), '0');
            if (preg_match('/^\d{5,10}$/', $numero) === 1) {
                $partes = array_map(
                    static fn (int $ini) => trim(preg_replace('/[^A-Za-zÁÉÍÓÚÑáéíóúñ ]/u', ' ', substr($limpio, $ini, 23))),
                    [104, 127, 58, 81]
                );
                $nombre = trim(preg_replace('/\s+/', ' ', implode(' ', $partes)));
                return ['codigo' => $numero, 'nombre' => mb_convert_case(mb_strtolower($nombre), MB_CASE_TITLE), 'formato' => 'CEDULA_PDF417'];
            }
        }

        if (preg_match('/ICCOL0*(\d{5,10})/', $compacto, $m) === 1) {
            return ['codigo' => $m[1], 'nombre' => '', 'formato' => 'CEDULA_MRZ'];
        }

        if (preg_match_all('/\d{6,10}/', $limpio, $m) && $m[0]) {
            usort($m[0], static fn ($a, $b) => strlen($b) <=> strlen($a));
            return ['codigo' => ltrim($m[0][0], '0') ?: $m[0][0], 'nombre' => '', 'formato' => 'NUMERO'];
        }

        return ['codigo' => '', 'nombre' => '', 'formato' => 'DESCONOCIDO'];
    }
}
