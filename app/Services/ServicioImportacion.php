<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\Validator;
use PDO;

/**
 * Importación de la base institucional de personas (CSV).
 *
 *  - VALIDAR revisa el archivo y cuenta qué pasaría, sin escribir personas.
 *  - APLICAR crea o actualiza en una sola transacción: o entran todas las filas válidas o ninguna.
 *  - La persona se encuentra por documento (y si no, por código de carnet). Nunca se borra a nadie,
 *    nunca se toca el saldo y no se desactiva a quien no venga en el archivo.
 *  - El registro de errores (tabla importaciones) guarda fila, campo y mensaje con el documento
 *    enmascarado: no copia datos personales del archivo.
 */
final class ServicioImportacion
{
    private const CAMPOS = ['documento', 'codigo_carnet', 'nombres', 'apellidos', 'correo', 'telefono', 'categoria', 'programa', 'activo'];

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return array{id_importacion:int,modo:string,filas:int,creadas:int,actualizadas:int,sin_cambios:int,rechazadas:int,errores:list<array{fila:int,documento:string,error:string}>}
     * @throws ReglaNegocioException
     */
    public function procesar(string $ruta, string $nombreArchivo, string $modo, ?int $usuario): array
    {
        $cfg = config('importacion');
        if (!in_array($modo, ['VALIDAR', 'APLICAR'], true)) {
            throw new ReglaNegocioException('MODO', 'Modo de importación no válido.');
        }
        if (!is_file($ruta) || filesize($ruta) === 0) {
            throw new ReglaNegocioException('ARCHIVO', 'El archivo está vacío o no se pudo leer.');
        }
        if (filesize($ruta) > (int) $cfg['max_bytes']) {
            throw new ReglaNegocioException('ARCHIVO', 'El archivo supera el tamaño máximo permitido.');
        }
        [$filas, $errores] = $this->leer($ruta, $cfg);

        $res = ['filas' => count($filas) + count(array_filter($errores, fn ($e) => $e['fila'] > 1)), 'creadas' => 0, 'actualizadas' => 0, 'sin_cambios' => 0];
        $this->pdo->beginTransaction();
        try {
            foreach ($filas as $f) {
                $resultado = $this->aplicarFila($f, $modo === 'APLICAR', $errores);
                if ($resultado !== null) {
                    $res[$resultado]++;
                }
            }
            $res['rechazadas'] = count($errores);
            usort($errores, fn ($a, $b) => $a['fila'] <=> $b['fila']);
            $id = $this->registrar(mb_substr(basename($nombreArchivo), 0, 160), hash_file('sha256', $ruta), $modo, $res, $errores, $usuario);
            if ($modo === 'APLICAR') {
                (new AuditService($this->pdo))->log('PERSONAS_IMPORTADAS', 'importaciones', $id, 'EXITO', null, $res, $usuario);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
        return ['id_importacion' => $id, 'modo' => $modo] + $res + ['errores' => $errores];
    }

    /** @return array{0:list<array<string,mixed>>,1:list<array{fila:int,documento:string,error:string}>} */
    private function leer(string $ruta, array $cfg): array
    {
        $h = fopen($ruta, 'rb');
        $primera = (string) fgets($h);
        $primera = preg_replace('/^\xEF\xBB\xBF/', '', $primera);
        $sep = substr_count($primera, ';') > substr_count($primera, ',') ? ';' : (substr_count($primera, "\t") > substr_count($primera, ',') ? "\t" : ',');
        $encabezado = array_map([self::class, 'clave'], str_getcsv(trim($primera), $sep, '"', ''));

        $indices = [];
        foreach ($cfg['columnas'] as $campo => $nombres) {
            foreach ($nombres as $n) {
                $i = array_search(self::clave($n), $encabezado, true);
                if ($i !== false) {
                    $indices[$campo] = $i;
                    break;
                }
            }
        }
        $faltan = array_diff(['documento', 'nombres', 'apellidos', 'categoria'], array_keys($indices));
        if ($faltan) {
            fclose($h);
            throw new ReglaNegocioException('COLUMNAS', 'Faltan columnas obligatorias en el archivo: ' . implode(', ', $faltan) . '. Revisa config/app.php (importacion.columnas).');
        }

        $filas = [];
        $errores = [];
        $vistos = ['documento' => [], 'codigo_carnet' => []];
        $n = 1;
        while (($linea = fgetcsv($h, 0, $sep, '"', '')) !== false) {
            $n++;
            if ($linea === [null] || implode('', $linea) === '') {
                continue;
            }
            if (count($filas) + count($errores) >= (int) $cfg['max_filas']) {
                $errores[] = ['fila' => $n, 'documento' => '', 'error' => 'Se alcanzó el máximo de filas por archivo; el resto no se procesó.'];
                break;
            }
            $fila = $this->normalizar($linea, $indices, $cfg);
            $fila['_fila'] = $n;
            $error = $this->validar($fila, $linea);
            foreach (['documento', 'codigo_carnet'] as $unico) {
                if ($error === null && $fila[$unico] !== '' && isset($vistos[$unico][$fila[$unico]])) {
                    $error = ($unico === 'documento' ? 'Documento' : 'Código de carnet') . ' repetido en el archivo (también en la fila ' . $vistos[$unico][$fila[$unico]] . ').';
                }
            }
            if ($error !== null) {
                $errores[] = ['fila' => $n, 'documento' => mask_doc((string) $fila['documento']), 'error' => $error];
                continue;
            }
            $vistos['documento'][$fila['documento']] = $n;
            if ($fila['codigo_carnet'] !== '') {
                $vistos['codigo_carnet'][$fila['codigo_carnet']] = $n;
            }
            $filas[] = $fila;
        }
        fclose($h);
        return [$filas, $errores];
    }

    private function normalizar(array $linea, array $indices, array $cfg): array
    {
        $v = static function (string $campo) use ($linea, $indices): string {
            if (!isset($indices[$campo])) {
                return '';
            }
            $txt = (string) ($linea[$indices[$campo]] ?? '');
            if (!mb_check_encoding($txt, 'UTF-8')) {
                $txt = mb_convert_encoding($txt, 'UTF-8', 'Windows-1252');
            }
            return trim(preg_replace('/\s+/u', ' ', $txt));
        };
        $categoria = self::clave($v('categoria'));
        $activo = $v('activo');
        return [
            'documento'     => strtoupper(str_replace([' ', '.'], '', $v('documento'))),
            'codigo_carnet' => strtoupper(str_replace(' ', '', $v('codigo_carnet'))),
            'nombres'       => $v('nombres'),
            'apellidos'     => $v('apellidos'),
            'correo'        => mb_strtolower($v('correo')),
            'telefono'      => $v('telefono'),
            'categoria'     => $cfg['categorias'][strtoupper($categoria)] ?? null,
            'categoria_txt' => $v('categoria'),
            'programa'      => mb_substr($v('programa'), 0, 120),
            'activo'        => $activo === '' ? null : (in_array(strtoupper(self::clave($activo)), $cfg['inactivos'], true) ? 0 : 1),
        ];
    }

    private function validar(array $f, array $linea): ?string
    {
        if ($f['categoria'] === null) {
            return $f['categoria_txt'] === '' ? 'Falta la categoría.' : 'Categoría no reconocida: «' . mb_substr($f['categoria_txt'], 0, 30) . '». Agrégala en importacion.categorias.';
        }
        $err = (new Validator())
            ->required('documento', $f['documento'], 'El documento')
            ->documento('documento', $f['documento'])
            ->required('nombres', $f['nombres'], 'Los nombres')
            ->max('nombres', $f['nombres'], 80, 'Los nombres')
            ->required('apellidos', $f['apellidos'], 'Los apellidos')
            ->max('apellidos', $f['apellidos'], 80, 'Los apellidos')
            ->email('correo', $f['correo'])
            ->max('correo', $f['correo'], 120, 'El correo')
            ->telefono('telefono', $f['telefono'])
            ->max('codigo_carnet', $f['codigo_carnet'], 40, 'El código del carnet')
            ->first();
        return $err;
    }

    /** @return 'creadas'|'actualizadas'|'sin_cambios'|null  null = fila rechazada (se agrega a $errores). */
    private function aplicarFila(array $f, bool $escribir, array &$errores): ?string
    {
        $existente = $this->uno('SELECT * FROM estudiantes WHERE documento = ? FOR UPDATE', [$f['documento']]);
        if ($existente === null && $f['codigo_carnet'] !== '') {
            $porCarnet = $this->uno('SELECT * FROM estudiantes WHERE codigo_carnet = ? FOR UPDATE', [$f['codigo_carnet']]);
            if ($porCarnet !== null) {
                $errores[] = ['fila' => $f['_fila'], 'documento' => mask_doc($f['documento']), 'error' => 'El código de carnet ya pertenece a otra persona con otro documento; revísalo manualmente.'];
                return null;
            }
        }
        if ($existente !== null && $f['codigo_carnet'] !== '') {
            $otro = $this->uno('SELECT id_estudiante FROM estudiantes WHERE codigo_carnet = ? AND id_estudiante <> ?', [$f['codigo_carnet'], $existente['id_estudiante']]);
            if ($otro !== null) {
                $errores[] = ['fila' => $f['_fila'], 'documento' => mask_doc($f['documento']), 'error' => 'El código de carnet ya está asignado a otra persona.'];
                return null;
            }
        }

        if ($existente === null) {
            if ($escribir) {
                $this->pdo->prepare(
                    "INSERT INTO estudiantes (documento, codigo_carnet, nombres, apellidos, correo, telefono, categoria, programa, activo, origen, sincronizado_en)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'INSTITUCIONAL', NOW())"
                )->execute([$f['documento'], $f['codigo_carnet'] ?: null, $f['nombres'], $f['apellidos'], $f['correo'] ?: null, $f['telefono'] ?: null, $f['categoria'], $f['programa'] ?: null, $f['activo'] ?? 1]);
            }
            return 'creadas';
        }

        // Solo se actualiza lo que el archivo trae; un campo opcional vacío no borra el dato existente.
        $nuevo = [
            'nombres'   => $f['nombres'],
            'apellidos' => $f['apellidos'],
            'categoria' => $f['categoria'],
        ];
        foreach (['codigo_carnet', 'correo', 'telefono', 'programa'] as $c) {
            if ($f[$c] !== '') {
                $nuevo[$c] = $f[$c];
            }
        }
        if ($f['activo'] !== null) {
            $nuevo['activo'] = $f['activo'];
        }
        $cambios = array_filter($nuevo, fn ($valor, $campo) => (string) $existente[$campo] !== (string) $valor, ARRAY_FILTER_USE_BOTH);
        $cambioOrigen = $existente['origen'] !== 'INSTITUCIONAL';
        if ($escribir) {
            $sets = array_map(fn ($c) => "$c = ?", array_keys($cambios));
            $sets[] = "origen = 'INSTITUCIONAL'";
            $sets[] = 'sincronizado_en = NOW()';
            $this->pdo->prepare('UPDATE estudiantes SET ' . implode(', ', $sets) . ' WHERE id_estudiante = ?')
                ->execute([...array_values($cambios), $existente['id_estudiante']]);
        }
        return $cambios || $cambioOrigen ? 'actualizadas' : 'sin_cambios';
    }

    private function registrar(string $archivo, string $sha, string $modo, array $r, array $errores, ?int $usuario): int
    {
        $this->pdo->prepare(
            'INSERT INTO importaciones (archivo, sha256, modo, filas, creadas, actualizadas, sin_cambios, rechazadas, errores_json, realizado_por)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $archivo, $sha, $modo, $r['filas'], $r['creadas'], $r['actualizadas'], $r['sin_cambios'], $r['rechazadas'],
            $errores ? json_encode(array_slice($errores, 0, 2000), JSON_UNESCAPED_UNICODE) : null, $usuario,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @return list<array<string,mixed>> */
    public function historial(int $limite = 20): array
    {
        return $this->pdo->query(
            'SELECT i.id_importacion, i.archivo, i.modo, i.filas, i.creadas, i.actualizadas, i.sin_cambios, i.rechazadas, i.creado_en, u.nombre AS usuario
               FROM importaciones i LEFT JOIN usuarios u ON u.id_usuario = i.realizado_por
              ORDER BY i.id_importacion DESC LIMIT ' . max(1, $limite)
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function errores(int $id): array
    {
        $st = $this->pdo->prepare('SELECT errores_json FROM importaciones WHERE id_importacion = ?');
        $st->execute([$id]);
        return json_decode((string) $st->fetchColumn(), true) ?: [];
    }

    private function uno(string $sql, array $p): ?array
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($p);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Nombre de columna o valor comparable: sin tildes, minúsculas, espacios como guion bajo. */
    private static function clave(string $s): string
    {
        $s = preg_replace('/^\xEF\xBB\xBF/', '', trim($s));
        $s = strtr(mb_strtolower($s), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        return preg_replace('/[\s\-]+/', '_', $s);
    }
}
