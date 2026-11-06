<?php
declare(strict_types=1);

use App\Helpers\Csrf;
use App\Helpers\View;
use App\Services\AuthService;

/** Lee un valor de config/app.php. */
function config(string $clave, mixed $defecto = null): mixed
{
    static $config = null;
    $config ??= require BASE_PATH . '/config/app.php';
    return $config[$clave] ?? $defecto;
}

/** Escapa texto para imprimirlo en HTML (XSS, sección 10). */
function e(string|int|float|null $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $ruta = ''): string
{
    return View::base() . '/' . ltrim($ruta, '/');
}

/** URL de un recurso con su fecha de modificación: al actualizar un CSS o JS, el navegador descarga la versión nueva. */
function asset(string $ruta): string
{
    $ruta = ltrim($ruta, '/');
    $archivo = BASE_PATH . '/assets/' . $ruta;
    return url('assets/' . $ruta) . (is_file($archivo) ? '?v=' . filemtime($archivo) : '');
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

function can(string ...$permisos): bool
{
    return AuthService::can(...$permisos);
}

/** Valor en pesos sin decimales cuando son cero: $ 5.000 */
function money(string|int|float|null $valor): string
{
    $numero = (float) $valor;
    $decimales = fmod($numero, 1.0) === 0.0 ? 0 : 2;
    return '$ ' . number_format($numero, $decimales, ',', '.');
}

function fecha(?string $fechaHora, string $formato = 'd/m/Y H:i'): string
{
    if ($fechaHora === null || $fechaHora === '') {
        return '—';
    }
    return (new DateTimeImmutable($fechaHora))->format($formato);
}

/** Tiempo transcurrido, solo informativo: no se usa para cobrar (sección 12). */
function duracion(string $desde, ?string $hasta = null): string
{
    $inicio = new DateTimeImmutable($desde);
    $fin = $hasta ? new DateTimeImmutable($hasta) : new DateTimeImmutable();
    $minutos = max(0, intdiv($fin->getTimestamp() - $inicio->getTimestamp(), 60));
    if ($minutos < 60) {
        return $minutos . ' min';
    }
    $horas = intdiv($minutos, 60);
    if ($horas < 48) {
        return $horas . ' h ' . ($minutos % 60) . ' min';
    }
    return intdiv($horas, 24) . ' días';
}

/** Oculta el documento salvo los últimos cuatro dígitos (privacidad, sección 10). */
function mask_doc(string $documento): string
{
    $largo = mb_strlen($documento);
    if ($largo <= 4) {
        return $documento;
    }
    return str_repeat('•', min(6, $largo - 4)) . mb_substr($documento, -4);
}

function old(array $datos, string $clave, string $defecto = ''): string
{
    $valor = $datos[$clave] ?? $defecto;
    return is_scalar($valor) ? (string) $valor : $defecto;
}

/** ESTUDIANTE → Estudiante, PROFESOR → Profesor */
function categoria_label(?string $categoria): string
{
    return ['ESTUDIANTE' => 'Estudiante', 'PROFESOR' => 'Profesor'][$categoria] ?? (string) $categoria;
}

function categoria_tag(?string $categoria): string
{
    return '<span class="cat-tag" data-cat="' . e($categoria) . '">' . e(categoria_label($categoria)) . '</span>';
}

function estado_badge(string $estado): string
{
    $clase = [
        'EN_CURSO' => 'abierta', 'CERRADA' => 'cerrada', 'ANULADA' => 'anulada',
        'PAGADO'   => 'pagada',  'ANULADO' => 'anulada',
        'EXITO'    => 'ok',      'DENEGADO' => 'err',    'ERROR' => 'err',
    ][$estado] ?? '';
    return '<span class="badge" data-state="' . $clase . '">' . e($estado) . '</span>';
}
