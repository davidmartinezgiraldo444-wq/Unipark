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
    return [
        'ESTUDIANTE' => 'Estudiante', 'PROFESOR' => 'Profesor',
        'TRABAJADOR' => 'Trabajador', 'VISITANTE' => 'Visitante',
    ][$categoria] ?? (string) $categoria;
}

/** CARRO → Automóvil, MOTO → Motocicleta… */
function tipo_label(?string $tipo): string
{
    return [
        'CARRO' => 'Automóvil', 'MOTO' => 'Motocicleta', 'BICICLETA' => 'Bicicleta',
        'PATINETA' => 'Patineta (scooter)', 'DESCONOCIDO' => 'Desconocido',
    ][$tipo] ?? (string) $tipo;
}

/** Estado del cobro diario de una visita, en palabras para Portería, Caja y la cuenta propia. */
function liquidacion_label(?string $liquidacion): string
{
    return [
        'EN_TOLERANCIA'        => 'En tolerancia',
        'SALIDA_EN_TOLERANCIA' => 'Salió en tolerancia · sin cobro',
        'NO_APLICA'            => 'Sin cobro',
        'COBRADA'              => 'Cobro del día',
        'CUBIERTA'             => 'Ya cobrado hoy',
        'DIFERENCIA'           => 'Diferencia por vehículo de tarifa mayor',
        'LEGADO'               => 'Pago por visita',
    ][$liquidacion] ?? (string) $liquidacion;
}

/** Tolerancia oficial del sistema (minutos). No se configura: es una regla de negocio. */
const TOLERANCIA_OFICIAL_MINUTOS = 15;

/**
 * Minutos de tolerancia. SIEMPRE 15 en el sistema; solo la copia de DEMOSTRACIÓN puede acelerarla (entre 1 y 15)
 * con TOLERANCIA_MINUTOS, para recorrer la ruta completa en vivo. Cualquier otro valor o instancia → 15.
 */
function tolerancia_minutos(string $instancia, ?string $valor): int
{
    if ($instancia !== 'demo' || $valor === null || !ctype_digit($valor)) {
        return TOLERANCIA_OFICIAL_MINUTOS;
    }
    return max(1, min(TOLERANCIA_OFICIAL_MINUTOS, (int) $valor));
}

/** «1 minuto» o «15 minutos». */
function minutos_texto(int $n): string
{
    return $n . ($n === 1 ? ' minuto' : ' minutos');
}

/** Verdadero si esta instancia usa una tolerancia distinta de la oficial (solo puede ocurrir en la demo). */
function tiempo_acelerado(): bool
{
    return (int) config('tolerancia_minutos') !== TOLERANCIA_OFICIAL_MINUTOS;
}

/** URL absoluta de una ruta propia: APP_URL si está configurada; si no, el esquema y el host de la solicitud. */
function url_absoluta(string $ruta): string
{
    $publica = (string) config('url_publica');
    if ($publica !== '') {
        return $publica . '/' . ltrim($ruta, '/');
    }
    $host = preg_replace('/[^A-Za-z0-9.:\-\[\]]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    return (\App\Helpers\Request::esHttps() ? 'https' : 'http') . '://' . $host . url($ruta);
}

/**
 * Ruta interna a la que se puede volver después de iniciar sesión, o null si no es segura.
 * Solo rutas propias en minúsculas («mi-saldo/recargas/12»): nunca otra web («//sitio», «https:»), ni login/logout.
 */
function volver_seguro(?string $ruta): ?string
{
    $ruta = ltrim(trim((string) $ruta), '/');
    if ($ruta === '' || strlen($ruta) > 120 || str_contains($ruta, '//')
        || preg_match('#^[a-z0-9][a-z0-9/-]*$#', $ruta) !== 1 || preg_match('#^(login|logout|cambiar-clave|api/)#', $ruta) === 1) {
        return null;
    }
    return $ruta;
}

/** Etiqueta de una fila de recarga: «Reembolsada» si Administración registró su reverso, si no su estado. */
function recarga_fila_label(array $r): string
{
    return !empty($r['reembolsada_en']) ? 'Reembolsada' : recarga_label($r['estado'] ?? null);
}

/** Estado de una recarga en línea, en palabras para la persona y Administración. */
function recarga_label(?string $estado): string
{
    return [
        'CREADA' => 'Sin pagar', 'PENDIENTE' => 'Pago en proceso', 'APROBADA' => 'Aprobada',
        'RECHAZADA' => 'Rechazada', 'ANULADA' => 'Anulada', 'ERROR' => 'Error en el pago',
        'CANCELADA' => 'Cancelada', 'EXPIRADA' => 'Vencida',
    ][$estado] ?? (string) $estado;
}

/** Medio de pago de un movimiento o pago (incluye Saldo y Pago en línea). */
function medio_label(?string $medio): string
{
    return ((array) config('medios_etiquetas'))[$medio] ?? (string) $medio;
}

function categoria_tag(?string $categoria): string
{
    return '<span class="cat-tag" data-cat="' . e($categoria) . '">' . e(categoria_label($categoria)) . '</span>';
}

function estado_badge(string $estado): string
{
    $clase = [
        'EN_CURSO' => 'abierta', 'CERRADA' => 'cerrada', 'ANULADA' => 'anulada',
        'PAGADO'   => 'pagada',  'ANULADO' => 'anulada', 'PENDIENTE' => 'abierta',
        'EXITO'    => 'ok',      'DENEGADO' => 'err',    'ERROR' => 'err',
        'APROBADA' => 'pagada',  'CREADA' => 'abierta',  'RECHAZADA' => 'err', 'CANCELADA' => 'anulada', 'EXPIRADA' => 'anulada',
    ][$estado] ?? '';
    return '<span class="badge" data-state="' . $clase . '">' . e($estado) . '</span>';
}
