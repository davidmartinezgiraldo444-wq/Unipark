<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Request;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Helpers\View;
use App\Repositories\EstudianteRepository;
use App\Repositories\PuestoCamaraRepository;
use App\Repositories\TarifaRepository;
use App\Repositories\VisitaRepository;
use App\Services\LectorDocumento;
use App\Services\ReglaNegocioException;
use App\Services\ReglasVisita;
use App\Services\ServicioEntrada;
use App\Services\ServicioSalida;

/**
 * Portería desde el celular: lectura del carnet o la cédula con la cámara, reconocimiento del
 * vehículo con IA en el navegador, entrada y salida. Las pantallas se comunican con estas
 * rutas en JSON; el servidor valida todo de nuevo.
 */
final class EscanerController extends Controller
{
    /** Pantalla del portero: carnet + vehículo (un celular) o solo carnet (dos celulares). */
    public function index(Request $request): Response
    {
        // Modo diagnóstico: SOLO en la copia de demostración (pruebas en el celular y presentación).
        $diagnostico = config('instancia') === 'demo';
        return $this->pantalla('porteria/escaner', 'Escáner de portería', $diagnostico ? ['diagnostico.js', 'escaner.js'] : ['escaner.js'], $diagnostico);
    }

    /** Segundo celular: cámara apuntando al carril que reconoce el vehículo de forma continua. */
    public function camara(Request $request): Response
    {
        return $this->pantalla('porteria/camara', 'Cámara de vehículos', ['camara-vehiculos.js']);
    }

    /**
     * Hoja imprimible de carnés FICTICIOS para la demostración: cada persona en Code 128, QR y un PDF417 con la
     * estructura que lee LectorDocumento (encabezado propio «UNIPARK-FICTICIO»: no copia el de ningún documento real).
     * En el sistema principal (hallazgo B7) solo la ve Administración; en la copia de demostración, el personal.
     */
    public function carnets(Request $request): Response
    {
        if (config('instancia') !== 'demo' && !can('USUARIO_GESTIONAR')) {
            return $this->notFound('Los carnés de prueba solo están disponibles en la copia de demostración.');
        }
        $personas = array_map(static function (array $p): array {
            $codigo = $p['codigo_carnet'] ?: $p['documento'];
            return $p + [
                'codigo'  => $codigo,
                'pdf417'  => ctype_digit($p['documento']) && strlen($p['documento']) <= 10
                    ? LectorDocumento::cedulaDePrueba($p['documento'], $p['apellidos'], $p['nombres']) : null,
            ];
        }, (new EstudianteRepository($this->db()))->conCarnet());
        return Response::html(View::render('porteria/carnets', [
            'titulo'   => 'Carnés de prueba',
            'personas' => $personas,
        ], 'layouts/plain'))->conBibliotecasDeCamara();
    }

    /** Identifica a la persona por el código leído y sugiere la acción: entrada, salida o elegir. */
    public function identificar(Request $request): Response
    {
        $lectura = LectorDocumento::interpretar(mb_substr($request->raw('codigo'), 0, 2000));
        $codigo = $lectura['codigo'];
        if ($codigo === '' || !Validator::esDocumento($codigo)) {
            return Response::json(['ok' => false, 'error' => 'No se reconoció un documento en el código leído. Escanea de nuevo o escribe el número.'], 422);
        }

        $persona = (new EstudianteRepository($this->db()))->findByCodigo($codigo);
        if ($persona === null) {
            // No registrado: es visitante. No se asume que sea estudiante, profesor ni trabajador.
            return Response::json([
                'ok'      => true,
                'tipo'    => 'VISITANTE',
                'codigo'  => $codigo,
                'formato' => $lectura['formato'],
                'persona' => ['id' => null, 'nombre' => $lectura['nombre'] ?: 'Visitante', 'categoria' => 'VISITANTE',
                              'categoria_label' => 'Visitante', 'documento' => mask_doc($codigo), 'nuevo' => true],
                'abiertas' => [],
                'accion'  => 'ENTRADA',
            ]);
        }

        if ((int) $persona['activo'] !== 1) {
            return Response::json([
                'ok'      => true,
                'tipo'    => 'INACTIVO',
                'codigo'  => $codigo,
                'persona' => $this->persona($persona),
                'abiertas' => [],
                'accion'  => 'NINGUNA',
                'mensaje' => 'La persona está inactiva. Remítela a Administración.',
            ]);
        }

        $abiertas = array_map([$this, 'visita'], (new VisitaRepository($this->db()))->abiertasDePersona((int) $persona['id_estudiante']));
        $visitante = $persona['categoria'] === ReglasVisita::VISITANTE;
        return Response::json([
            'ok'       => true,
            'tipo'     => $visitante ? 'VISITANTE' : 'REGISTRADO',
            'codigo'   => $codigo,
            'formato'  => $lectura['formato'],
            'persona'  => $this->persona($persona),
            'abiertas' => $abiertas,
            // Una persona registrada con visita abierta está saliendo. Un visitante puede tener varias
            // visitas abiertas: el portero elige (sin advertencias ni restricciones, requerimiento 6).
            'accion'   => $abiertas ? ($visitante ? 'ELEGIR' : 'SALIDA') : 'ENTRADA',
        ]);
    }

    public function entrada(Request $request): Response
    {
        $tipo = $request->input('tipo');
        // Entrada manual de Portería: el tipo lo elige una persona, así que SIEMPRE queda como MANUAL. El navegador no
        // puede declararla «IA» (hallazgo B8 de la auditoría): la IA solo entra por el flujo del punto de acceso.
        $origen = 'MANUAL';
        $servicio = new ServicioEntrada($this->db());
        try {
            $idPersona = $request->int('id_estudiante');
            $visita = $idPersona !== null
                ? $servicio->registrar($idPersona, $tipo, $this->usuarioId(), $origen)
                : $servicio->registrarVisitante($request->input('codigo'), mb_substr($request->input('nombre'), 0, 80), $tipo, $this->usuarioId(), $origen);
        } catch (ReglaNegocioException $e) {
            return Response::json(['ok' => false, 'codigo' => $e->codigo, 'error' => $e->getMessage()], 422);
        }
        $mensaje = $visita['categoria'] === ReglasVisita::VISITANTE
            ? 'Entrada de visitante registrada. Los visitantes no generan cobro.'
            : match ($visita['liquidacion']) {
                'NO_APLICA' => tipo_label($visita['tipo_vehiculo']) . ': entrada registrada sin tarifa.',
                'CUBIERTA'  => 'Entrada registrada. Lo pagado hoy ya cubre este vehículo: no se cobra de nuevo.',
                'EN_TOLERANCIA' => (new \App\Repositories\CobroRepository($this->db()))->delDia((int) $visita['id_estudiante'], substr($visita['entrada_en'], 0, 10)) !== null
                    ? sprintf('Entrada registrada. Vehículo de tarifa mayor que lo pagado hoy: si pasa de %s se cobra solo la diferencia.', minutos_texto((int) config('tolerancia_minutos')))
                    : sprintf('Entrada registrada. Tiene %s de tolerancia sin cobro; después se cobra %s del día.', minutos_texto((int) config('tolerancia_minutos')), money($visita['tarifa_aplicada'])),
                default     => sprintf('Entrada registrada. Tiene %s de tolerancia sin cobro; después se cobra %s del día.',
                    minutos_texto((int) config('tolerancia_minutos')), money($visita['tarifa_aplicada'])),
            };
        return Response::json(['ok' => true, 'mensaje' => $mensaje, 'visita' => $this->visita($visita)]);
    }

    /** Salida: solo consulta el estado del pago y autoriza o deniega. */
    public function salida(Request $request): Response
    {
        $idVisita = $request->int('id_visita');
        if ($idVisita === null) {
            return Response::json(['ok' => false, 'error' => 'No se indicó la visita.'], 422);
        }
        try {
            $visita = (new ServicioSalida($this->db()))->autorizar($idVisita, $this->usuarioId());
        } catch (ReglaNegocioException $e) {
            $v = (new VisitaRepository($this->db()))->find($idVisita);
            return Response::json([
                'ok' => true, 'autorizada' => false, 'codigo' => $e->codigo, 'mensaje' => $e->getMessage(),
                'visita' => $v ? $this->visita($v) : null,
            ]);
        }
        $mensaje = match ($visita['liquidacion']) {
            'SALIDA_EN_TOLERANCIA' => 'Salida autorizada dentro de la tolerancia: sin cobro.',
            'NO_APLICA'            => 'Salida autorizada. Sin cobro.',
            default                => 'Salida autorizada. Pago verificado.',
        };
        return Response::json(['ok' => true, 'autorizada' => true, 'mensaje' => $mensaje, 'visita' => $this->visita($visita)]);
    }

    /** El celular de cámara publica la clase que ve (solo el tipo y su confianza; nunca la imagen). */
    public function deteccion(Request $request): Response
    {
        $tipo = $request->input('tipo');
        $confianza = \App\Services\ReglasAcceso::probabilidad($request->input('confianza', '0')) ?? 0.0;
        $segunda = \App\Services\ReglasAcceso::probabilidad($request->input('segunda', '0')) ?? 0.0;
        $puesto = $request->int('puesto') ?? 1;
        if (!in_array($tipo, array_merge(ReglasVisita::TIPOS, ['DESCONOCIDO']), true)) {
            return Response::json(['ok' => false, 'codigo' => 'TIPO_INVALIDO', 'error' => 'Tipo de vehículo no válido.'], 422);
        }
        (new PuestoCamaraRepository($this->db()))->actualizar($puesto, $tipo, $confianza, $this->usuarioId(), $segunda);
        return Response::json(['ok' => true]);
    }

    public function ultima(Request $request): Response
    {
        $fila = (new PuestoCamaraRepository($this->db()))->ultima($request->int('puesto') ?? 1);
        $ia = config('ia');
        $vigente = $fila && $fila['tipo_detectado'] !== null && $fila['hace_ms'] !== null && (int) $fila['hace_ms'] <= $ia['vigencia_ms'];
        return Response::json([
            'ok'        => true,
            'tipo'      => $vigente ? $fila['tipo_detectado'] : null,
            'etiqueta'  => $vigente ? tipo_label($fila['tipo_detectado']) : null,
            'confianza' => $vigente ? (float) $fila['confianza'] : null,
            'hace_ms'   => $fila['hace_ms'] !== null ? (int) $fila['hace_ms'] : null,
            'vigente'   => $vigente,
        ]);
    }

    private function pantalla(string $vista, string $titulo, array $scripts, bool $diagnostico = false): Response
    {
        $ia = config('ia');
        $modelo = trim((string) $ia['modelo_tm'], '/');
        return Response::html(View::render($vista, [
            'titulo'     => $titulo,
            'scripts'    => $scripts,
            'diagnostico' => $diagnostico,
            'puestos'    => (new PuestoCamaraRepository($this->db()))->todos(),
            'tarifas'    => (new TarifaRepository($this->db()))->vigentes(),
            'config'     => [
                'base'        => url(''),
                'csrf'        => \App\Helpers\Csrf::token(),
                'umbral'      => (float) $ia['umbral'],
                'margen'      => (float) $ia['margen'],
                'esperaS'     => (int) $ia['espera_s'],
                'capturaPortero' => (bool) $ia['captura_portero'],
                'estableMs'   => (int) $ia['estable_ms'],
                'vigenciaMs'  => (int) $ia['vigencia_ms'],
                'tolerancia'  => (int) config('tolerancia_minutos'),
                // Si existe el modelo exportado de Teachable Machine se usa; si no, el preentrenado COCO-SSD.
                'modeloTm'    => is_file(BASE_PATH . '/' . $modelo . '/model.json') ? url($modelo . '/') : null,
                // Solo los tipos habilitados (config tipos_habilitados): la IA no propone otros.
                'tipos'       => array_combine((array) config('tipos_habilitados'), array_map('tipo_label', (array) config('tipos_habilitados'))),
            ],
        ], 'layouts/movil'))->conBibliotecasDeCamara(true);
    }

    private function persona(array $p): array
    {
        return [
            'id'              => (int) $p['id_estudiante'],
            'nombre'          => trim($p['nombres'] . ' ' . $p['apellidos']),
            'categoria'       => $p['categoria'],
            'categoria_label' => categoria_label($p['categoria']),
            'documento'       => mask_doc($p['documento']),
            'saldo'           => $p['categoria'] === ReglasVisita::VISITANTE ? null : (float) $p['saldo'],
            'nuevo'           => false,
        ];
    }

    private function visita(array $v): array
    {
        $estadoPago = match (true) {
            $v['liquidacion'] === 'LEGADO'                                   => (int) $v['pagada'] === 1 ? 'PAGADO' : 'PENDIENTE',
            in_array($v['liquidacion'], ['NO_APLICA', 'SALIDA_EN_TOLERANCIA'], true) => 'NO_REQUIERE',
            $v['liquidacion'] === 'EN_TOLERANCIA'                            => 'EN_TOLERANCIA',
            default                                                           => $v['cobro_estado'] === 'ANULADO' ? 'NO_REQUIERE' : (string) $v['cobro_estado'],
        };
        return [
            'id'               => (int) $v['id_visita'],
            'tipo'             => $v['tipo_vehiculo'],
            'tipo_label'       => tipo_label($v['tipo_vehiculo']),
            'origen'           => $v['tipo_origen'],
            'categoria'        => $v['categoria'],
            'entrada'          => fecha($v['entrada_en'], 'H:i'),
            'tolerancia_hasta' => $v['tolerancia_hasta'],
            'segundos_tolerancia' => $v['tolerancia_hasta'] && $v['liquidacion'] === 'EN_TOLERANCIA'
                ? max(0, strtotime($v['tolerancia_hasta']) - time()) : 0,
            'liquidacion'      => $v['liquidacion'],
            'liquidacion_label' => liquidacion_label($v['liquidacion']),
            'estado'           => $v['estado'],
            'estado_pago'      => $estadoPago,
            'valor'            => $v['cobro_valor'] ?? $v['tarifa_aplicada'],
        ];
    }
}
