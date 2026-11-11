<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Repositories\EstudianteRepository;
use App\Repositories\EventoRepository;
use App\Repositories\PuestoCamaraRepository;
use App\Repositories\VisitaRepository;
use PDO;
use PDOException;

/**
 * Punto de acceso: identidad → vehículo → confirmación → visita (docs/flujo-acceso-definitivo.md).
 *
 * Reglas que garantiza el SERVIDOR (no el navegador):
 *  - La identidad se valida primero, en la entrada y en la salida.
 *  - El tipo de vehículo solo lo fija una clasificación confiable de la IA (umbral y margen del servidor) o
 *    un portero autenticado sobre un evento pendiente concreto. El punto de acceso no puede elegirlo.
 *  - La tarifa, la tolerancia y los importes salen de la base de datos.
 *  - Cada evento se procesa una sola vez (fila bloqueada y estados finales inmutables): los reintentos
 *    devuelven el mismo resultado y no duplican entradas, salidas ni cobros.
 */
final class ServicioAcceso
{
    private EventoRepository $eventos;
    private EstudianteRepository $personas;
    private VisitaRepository $visitas;
    private AuditService $audit;
    private IdentificadorVehiculo $identificador;

    public function __construct(private PDO $pdo, ?IdentificadorVehiculo $identificador = null)
    {
        $this->eventos = new EventoRepository($pdo);
        $this->personas = new EstudianteRepository($pdo);
        $this->visitas = new VisitaRepository($pdo);
        $this->audit = new AuditService($pdo);
        $this->identificador = $identificador ?? new SinIdentificadorVehiculo();
    }

    // ============================================================ 1. Identidad

    /** @throws ReglaNegocioException */
    public function identificar(string $codigoLeido, string $nombreLeido, int $idPuesto, int $usuario): array
    {
        $lectura = LectorDocumento::interpretar(mb_substr($codigoLeido, 0, 2000));
        $codigo = $lectura['codigo'];
        if ($codigo === '' || !\App\Helpers\Validator::esDocumento($codigo)) {
            throw new ReglaNegocioException('DOCUMENTO', 'No se reconoció un documento en el código leído. Escanea de nuevo.');
        }
        if (!(new PuestoCamaraRepository($this->pdo))->existe($idPuesto)) {
            throw new ReglaNegocioException('PUESTO', 'El punto de acceso indicado no existe.');
        }
        $nombre = mb_substr(trim(preg_replace('/\s+/u', ' ', $nombreLeido !== '' ? $nombreLeido : $lectura['nombre'])), 0, 80);

        for ($intento = 0; $intento < 2; $intento++) {
            $this->pdo->beginTransaction();
            try {
                $id = $this->identificarEnTransaccion($codigo, $nombre, $idPuesto, $usuario);
                $this->pdo->commit();
                return $this->estadoPublico($id);
            } catch (PDOException $e) {
                $this->pdo->rollBack();
                // Dos escaneos simultáneos en el mismo punto: el índice único lo impidió. Se reintenta una vez.
                if (!Database::isDuplicate($e) || $intento === 1) {
                    throw $e;
                }
            } catch (\Throwable $e) {
                $this->pdo->rollBack();
                throw $e;
            }
        }
        throw new ReglaNegocioException('OCUPADO', 'El punto de acceso está procesando otro escaneo. Intenta de nuevo.');
    }

    private function identificarEnTransaccion(string $codigo, string $nombre, int $idPuesto, int $usuario): int
    {
        $persona = $this->personas->findByCodigo($codigo);
        $captura = $this->eventos->enCapturaDePuesto($idPuesto, true);

        // Identidad no registrada: no entra sola; el portero decide (visitante o rechazo).
        if ($persona === null) {
            $abierto = $this->eventos->abiertoDeDocumento($codigo, true);
            if ($abierto !== null) {
                return (int) $abierto['id_evento']; // Reintento: el mismo aviso, sin duplicarlo.
            }
            $this->liberarPuesto($captura, $usuario);
            $id = $this->eventos->crear([
                'tipo' => 'ENTRADA', 'id_puesto' => $idPuesto, 'documento_leido' => $codigo, 'nombre_leido' => $nombre ?: null,
                'estado' => 'PENDIENTE_PORTERO', 'motivo' => 'IDENTIDAD_NO_REGISTRADA',
                'mensaje' => 'Identidad no registrada en la base institucional. Portería decide.', 'creado_por' => $usuario,
            ]);
            $this->audit->log('ACCESO_IDENTIDAD_NO_REGISTRADA', 'eventos_acceso', $id, 'DENEGADO', null, ['puesto' => $idPuesto], $usuario);
            return $id;
        }

        $idPersona = (int) $persona['id_estudiante'];
        $persona = $this->personas->find($idPersona, true);
        if ((int) $persona['activo'] !== 1) {
            $this->liberarPuesto($captura, $usuario);
            $id = $this->eventos->crear([
                'tipo' => 'ENTRADA', 'id_puesto' => $idPuesto, 'id_estudiante' => $idPersona,
                'estado' => 'RECHAZADO', 'motivo' => 'PERSONA_NO_AUTORIZADA', 'resultado' => 'NO_AUTORIZADA',
                'mensaje' => 'La persona no está autorizada (inactiva). Remítela a Administración.', 'creado_por' => $usuario,
            ]);
            $this->audit->log('ACCESO_NO_AUTORIZADO', 'eventos_acceso', $id, 'DENEGADO', 'Persona inactiva', ['id_estudiante' => $idPersona], $usuario);
            return $id;
        }

        $abierto = $this->eventos->abiertoDePersona($idPersona, true);
        if ($abierto !== null) {
            if ($abierto['estado'] === 'EN_CAPTURA' && (int) $abierto['id_puesto'] !== $idPuesto) {
                $this->expirar($abierto, 'CAPTURA_REEMPLAZADA', 'La persona se escaneó en otro punto de acceso.', $usuario);
            } else {
                return (int) $abierto['id_evento']; // Reintento o evento esperando al portero.
            }
        }
        if ($captura !== null && (int) $captura['id_evento'] !== (int) ($abierto['id_evento'] ?? 0)) {
            $this->liberarPuesto($captura, $usuario);
        }

        $abiertas = $this->visitas->abiertasDePersona($idPersona);
        $tipo = $abiertas ? 'SALIDA' : 'ENTRADA';
        $id = $this->eventos->crear([
            'tipo' => $tipo, 'id_puesto' => $idPuesto, 'id_estudiante' => $idPersona,
            // Un visitante puede tener varias visitas abiertas: se elige la que coincide con el vehículo detectado.
            'id_visita' => count($abiertas) === 1 ? (int) $abiertas[0]['id_visita'] : null,
            'estado' => 'EN_CAPTURA', 'creado_por' => $usuario,
            'mensaje' => $tipo === 'SALIDA' ? 'Identidad validada. Verificando el vehículo de salida.' : 'Identidad validada. Identificando el vehículo.',
        ]);
        $this->audit->log('ACCESO_IDENTIFICADO', 'eventos_acceso', $id, 'EXITO', null, ['tipo' => $tipo, 'puesto' => $idPuesto], $usuario);
        return $id;
    }

    // ============================================================ 2. Vehículo (IA)

    /**
     * Resultado de la cámara para un evento EN_CAPTURA. $fuente: CELULAR (este mismo dispositivo) o PUESTO
     * (el celular de cámara del puesto; el servidor lee su detección, solo si es posterior al escaneo).
     * $final: el punto de acceso agotó la espera sin una detección confiable.
     * @throws ReglaNegocioException
     */
    public function clasificar(int $idEvento, int $usuario, string $fuente, string $tipo, mixed $confianza, mixed $segunda, bool $final = false, ?string $captura = null): array
    {
        $this->pdo->beginTransaction();
        try {
            $ev = $this->eventos->find($idEvento, true);
            if ($ev === null) {
                throw new ReglaNegocioException('NO_EXISTE', 'El evento de acceso no existe.');
            }
            if ((int) $ev['creado_por'] !== $usuario) {
                throw new ReglaNegocioException('AJENO', 'Este evento pertenece a otro punto de acceso.');
            }
            if ($ev['estado'] !== 'EN_CAPTURA') {
                $this->pdo->commit();
                return $this->estadoPublico($idEvento); // Reintento: no se vuelve a procesar.
            }

            $fuente = $fuente === 'PUESTO' ? 'PUESTO' : 'CELULAR';
            if ($fuente === 'PUESTO') {
                $det = (new PuestoCamaraRepository($this->pdo))->ultima((int) $ev['id_puesto']);
                $vigente = $det && $det['detectado_en'] !== null && $det['detectado_en'] >= $ev['creado_en']
                    && (int) $det['hace_ms'] <= (int) config('ia')['vigencia_ms'];
                if (!$vigente && !$final) {
                    $this->pdo->commit();
                    return $this->estadoPublico($idEvento) + ['esperando' => true];
                }
                $tipo = $vigente ? (string) $det['tipo_detectado'] : 'DESCONOCIDO';
                $confianza = $vigente ? $det['confianza'] : null;
                $segunda = $vigente ? $det['segunda'] : null;
            }

            $tipo = ReglasAcceso::normalizarTipo($tipo);
            $conf = ReglasAcceso::probabilidad($confianza);
            $seg = ReglasAcceso::probabilidad($segunda);
            $ia = (array) config('ia');
            $habilitados = (array) config('tipos_habilitados');
            $this->eventos->registrarDeteccion($idEvento, $tipo, $conf, $seg, $fuente);
            $confiable = ReglasAcceso::confiable($tipo, $conf, $seg, (float) $ia['umbral'], (float) $ia['margen'], $habilitados);

            if (!$confiable) {
                $guardada = $this->guardarCaptura($idEvento, $captura);
                $mensaje = $tipo === 'DESCONOCIDO' || !in_array($tipo, $habilitados, true)
                    ? 'La IA no pudo clasificar el vehículo.'
                    : sprintf('La IA no alcanzó la confianza mínima (%s: %d %%).', tipo_label($tipo), (int) round(($conf ?? 0) * 100));
                $this->eventos->pasarAPortero($idEvento, 'VEHICULO_NO_CLASIFICADO', $mensaje, $guardada);
                $this->audit->log('ACCESO_A_PORTERO', 'eventos_acceso', $idEvento, 'EXITO', 'Clasificación no confiable', [
                    'tipo_detectado' => $tipo, 'confianza' => $conf, 'segunda' => $seg, 'fuente' => $fuente,
                ], $usuario);
                $this->pdo->commit();
                return $this->estadoPublico($idEvento);
            }

            // Origen honesto: la inferencia se hizo en el navegador del dispositivo autenticado (ACCESO_KIOSCO, dueño del
            // evento). El servidor revalida tipo habilitado, umbral y margen, pero NO puede comprobar que la confianza
            // sea real: queda registrada como «reportada por el dispositivo», nunca como una verificación del servidor.
            $this->audit->log('ACCESO_CLASIFICADO_IA', 'eventos_acceso', $idEvento, 'EXITO', 'Inferencia en el dispositivo', [
                'tipo' => $tipo, 'confianza_reportada' => $conf, 'segunda_reportada' => $seg, 'fuente' => $fuente,
                'origen' => 'INFERENCIA_EN_NAVEGADOR', 'umbral' => (float) $ia['umbral'], 'margen' => (float) $ia['margen'],
            ], $usuario);
            if (!empty($ia['confirmar_portero'])) {
                // Regla de Portería (10/11/2026): la detección de la IA es una SUGERENCIA. El portero la confirma o la
                // cambia (Carro / Moto / Bicicleta) en su panel; la persona en el punto de acceso nunca elige.
                $this->eventos->pasarAPortero($idEvento, 'VEHICULO_NO_CLASIFICADO',
                    sprintf('La IA detectó %s (%d %%). Confirma el vehículo o cámbialo.', mb_strtolower(tipo_label($tipo)), (int) round($conf * 100)));
                $this->pdo->commit();
                return $this->estadoPublico($idEvento);
            }
            $this->confirmarConTipo($ev, $tipo, 'IA', $usuario, null);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
        return $this->estadoPublico($idEvento);
    }

    // ============================================================ 3. Portero

    /**
     * El portero resuelve un evento pendiente: CLASIFICAR (con el tipo real), ADMITIR (visitante con su
     * vehículo) o RECHAZAR. Solo eventos PENDIENTE_PORTERO; si otro portero ya lo resolvió, se informa.
     * @throws ReglaNegocioException
     */
    public function resolver(int $idEvento, int $portero, string $accion, string $tipo = '', string $nota = ''): array
    {
        $accion = strtoupper($accion);
        if (!in_array($accion, ['CLASIFICAR', 'ADMITIR', 'RECHAZAR'], true)) {
            throw new ReglaNegocioException('ACCION', 'Acción no válida.');
        }
        if ($accion !== 'RECHAZAR' && !in_array($tipo, (array) config('tipos_habilitados'), true)) {
            throw new ReglaNegocioException('TIPO_INVALIDO', 'Selecciona automóvil, motocicleta o bicicleta.');
        }

        $this->pdo->beginTransaction();
        try {
            $ev = $this->eventos->find($idEvento, true);
            if ($ev === null) {
                throw new ReglaNegocioException('NO_EXISTE', 'El evento de acceso no existe.');
            }
            if ($ev['estado'] !== 'PENDIENTE_PORTERO') {
                $this->pdo->commit();
                return $this->estadoPublico($idEvento) + ['ya_resuelto' => true];
            }

            if ($accion === 'RECHAZAR') {
                $this->eventos->cerrar($idEvento, 'RECHAZADO', [
                    'resultado' => 'RECHAZADO_PORTERIA', 'resuelto_por' => $portero,
                    'mensaje' => 'Rechazado por Portería' . ($nota !== '' ? ': ' . mb_substr($nota, 0, 200) : '.'),
                ]);
            } elseif ($ev['motivo'] === 'IDENTIDAD_NO_REGISTRADA') {
                // Admitir como visitante: no paga y no se considera estudiante, profesor ni trabajador.
                $persona = $this->personas->findByCodigo((string) $ev['documento_leido']);
                if ($persona === null) {
                    $idPersona = $this->personas->crearVisitante((string) $ev['documento_leido'], (string) ($ev['nombre_leido'] ?? ''));
                    $this->audit->log('VISITANTE_REGISTRADO', 'estudiantes', $idPersona, 'EXITO', null, ['id_evento' => $idEvento], $portero);
                } else {
                    $idPersona = (int) $persona['id_estudiante'];
                }
                $this->confirmarConTipo($ev + ['id_estudiante_resuelto' => $idPersona], $tipo, 'MANUAL', $portero, $portero);
            } else {
                // El portero CONFIRMA la sugerencia confiable de la IA (mismo tipo) o la CAMBIA (clasificación manual).
                $confirmaIa = $accion === 'CLASIFICAR' && $this->sugerenciaConfiable($ev) && $tipo === $ev['tipo_detectado'];
                $this->confirmarConTipo($ev, $tipo, $confirmaIa ? 'IA' : 'MANUAL', $portero, $portero);
            }
            $this->audit->log(!empty($confirmaIa) ? 'ACCESO_IA_CONFIRMADA' : 'ACCESO_RESUELTO_MANUAL', 'eventos_acceso', $idEvento, 'EXITO', $nota !== '' ? mb_substr($nota, 0, 200) : null, [
                'accion' => $accion, 'tipo_elegido' => $tipo ?: null, 'tipo_detectado' => $ev['tipo_detectado'],
                'confianza' => $ev['confianza'], 'motivo' => $ev['motivo'], 'evento' => $ev['tipo'],
            ], $portero);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
        $this->borrarCaptura($idEvento);
        return $this->estadoPublico($idEvento);
    }

    /**
     * Aplica el tipo confirmado (IA confiable o portero) dentro de la transacción del evento.
     * Entrada: registra la visita (tarifa y tolerancia del servidor). Salida: compara con el ingreso y, si
     * coincide, solo CONSULTA el estado del pago.
     */
    private function confirmarConTipo(array $ev, string $tipo, string $metodo, int $usuario, ?int $portero): void
    {
        $idEvento = (int) $ev['id_evento'];
        if ($ev['tipo'] === 'ENTRADA') {
            $idPersona = (int) ($ev['id_estudiante_resuelto'] ?? $ev['id_estudiante']);
            try {
                $idVisita = (new ServicioEntrada($this->pdo))->registrarEnTransaccion($idPersona, $tipo, $usuario, $metodo, $idEvento);
            } catch (ReglaNegocioException $e) {
                $this->eventos->cerrar($idEvento, 'RECHAZADO', [
                    'resultado' => $e->codigo, 'mensaje' => $e->getMessage(), 'resuelto_por' => $portero,
                ]);
                return;
            }
            $visita = $this->visitas->find($idVisita);
            $this->eventos->cerrar($idEvento, 'CONFIRMADO', [
                'tipo_confirmado' => $tipo, 'metodo' => $metodo, 'id_visita' => $idVisita, 'resuelto_por' => $portero,
                'resultado' => $ev['motivo'] === 'IDENTIDAD_NO_REGISTRADA' ? 'VISITANTE_ADMITIDO' : 'ENTRADA_REGISTRADA',
                'mensaje' => self::mensajeEntrada($visita),
            ]);
            return;
        }

        // SALIDA: la visita del ingreso con la misma categoría (sin identificación individual, solo categoría).
        $visita = $this->visitaDeSalida($ev, $tipo);
        if ($visita === null) {
            $ingreso = $ev['id_visita'] ? $this->visitas->find((int) $ev['id_visita']) : null;
            $texto = sprintf('Se detectó %s, pero el ingreso fue en %s. La visita sigue abierta.',
                mb_strtolower(tipo_label($tipo)), $ingreso ? mb_strtolower(tipo_label($ingreso['tipo_vehiculo'])) : 'otro vehículo');
            if ($metodo === 'IA' && $portero === null) {
                $this->eventos->pasarAPortero($idEvento, 'VEHICULO_NO_COINCIDE', $texto);
                $this->audit->log('ACCESO_A_PORTERO', 'eventos_acceso', $idEvento, 'EXITO', 'El vehículo no coincide con el ingreso', ['tipo_detectado' => $tipo], $usuario);
            } else {
                $this->eventos->cerrar($idEvento, 'RECHAZADO', [
                    'tipo_confirmado' => $tipo, 'metodo' => 'MANUAL', 'resuelto_por' => $portero,
                    'resultado' => 'VEHICULO_NO_COINCIDE', 'mensaje' => $texto,
                ]);
            }
            return;
        }
        $r = (new ServicioSalida($this->pdo))->decidirEnTransaccion((int) $visita['id_visita'], $usuario, $idEvento);
        $this->eventos->cerrar($idEvento, 'CONFIRMADO', [
            'tipo_confirmado' => $tipo, 'metodo' => $metodo, 'id_visita' => (int) $visita['id_visita'], 'resuelto_por' => $portero,
            'resultado' => $r['autorizada'] ? 'SALIDA_AUTORIZADA' : ($r['codigo'] === 'COBRO_SIN_PROCESAR' ? 'SALIDA_INCIDENCIA' : 'SALIDA_DENEGADA'),
            'mensaje' => $r['mensaje'],
        ]);
    }

    private function visitaDeSalida(array $ev, string $tipo): ?array
    {
        $candidatas = $ev['id_visita']
            ? array_filter([$this->visitas->find((int) $ev['id_visita'])])
            : $this->visitas->abiertasDePersona((int) $ev['id_estudiante']);
        foreach ($candidatas as $v) {
            if ($v['estado'] === 'EN_CURSO' && ReglasAcceso::coincideCategoria($v['tipo_vehiculo'], $tipo)) {
                return $v;
            }
        }
        return null;
    }

    // ============================================================ 4. Consulta, pendientes y expiración

    /** Estado del evento para el punto de acceso (datos mínimos: sin documento completo). */
    /**
     * ¿El evento pendiente trae una sugerencia CONFIABLE de la IA (tipo habilitado, umbral y margen)? Entonces el
     * portero solo confirma o cambia; si no, la IA no lo identificó y el portero elige.
     */
    public function sugerenciaConfiable(array $ev): bool
    {
        $ia = (array) config('ia');
        return $ev['motivo'] === 'VEHICULO_NO_CLASIFICADO' && $ev['tipo_detectado'] !== null
            && ReglasAcceso::confiable((string) $ev['tipo_detectado'], $ev['confianza'] !== null ? (float) $ev['confianza'] : null,
                $ev['segunda'] !== null ? (float) $ev['segunda'] : null, (float) $ia['umbral'], (float) $ia['margen'], (array) config('tipos_habilitados'));
    }

    public function estadoPublico(int $idEvento): array
    {
        $ev = $this->eventos->find($idEvento);
        $persona = $ev['id_estudiante'] ? $this->personas->find((int) $ev['id_estudiante']) : null;
        $visita = $ev['id_visita'] ? $this->visitas->find((int) $ev['id_visita']) : null;
        return [
            'ok'        => true,
            'id'        => (int) $ev['id_evento'],
            'tipo'      => $ev['tipo'],
            'estado'    => $ev['estado'],
            'motivo'    => $ev['motivo'],
            'resultado' => $ev['resultado'],
            'mensaje'   => $ev['mensaje'],
            'persona'   => $persona ? [
                'nombre'          => trim($persona['nombres'] . ' ' . $persona['apellidos']),
                'categoria'       => $persona['categoria'],
                'categoria_label' => categoria_label($persona['categoria']),
                'documento'       => mask_doc($persona['documento']),
            ] : ($ev['documento_leido'] ? ['nombre' => 'No registrado', 'categoria' => null, 'categoria_label' => 'Sin registro', 'documento' => mask_doc($ev['documento_leido'])] : null),
            'vehiculo'  => $ev['tipo_confirmado'] ? tipo_label($ev['tipo_confirmado']) : null,
            'metodo'    => $ev['metodo'],
            // Esperando que el portero confirme lo que detectó la IA (el celular muestra el tipo, nunca botones).
            'por_confirmar' => $ev['estado'] === 'PENDIENTE_PORTERO' && $this->sugerenciaConfiable($ev) ? tipo_label((string) $ev['tipo_detectado']) : null,
            'visita'    => $visita ? [
                'tipo'             => tipo_label($visita['tipo_vehiculo']),
                'entrada'          => fecha($visita['entrada_en'], 'H:i'),
                'tolerancia_hasta' => $visita['tolerancia_hasta'] ? fecha($visita['tolerancia_hasta'], 'H:i') : null,
                'liquidacion'      => liquidacion_label($visita['liquidacion']),
            ] : null,
            'detalle'   => $this->detalleResultado($ev, $persona, $visita),
            'deuda'     => $visita !== null && in_array($ev['resultado'], ['SALIDA_DENEGADA', 'SALIDA_INCIDENCIA'], true),
        ];
    }

    /**
     * Detalle que se muestra al terminar el proceso (entrada o salida), calculado SIEMPRE en el servidor.
     * @return list<array{0:string,1:string}> pares etiqueta/valor
     */
    private function detalleResultado(array $ev, ?array $persona, ?array $visita): array
    {
        if (!in_array($ev['estado'], ['CONFIRMADO', 'RECHAZADO'], true) || $visita === null) {
            return [];
        }
        $d = [];
        if ($persona !== null) {
            $d[] = ['Persona', trim($persona['nombres'] . ' ' . $persona['apellidos']) . ' · ' . mask_doc($persona['documento'])];
        }
        $metodo = match (true) {
            $ev['metodo'] === 'MANUAL'                          => 'confirmado por Portería',
            $ev['metodo'] === 'IA' && $ev['resuelto_por'] !== null => 'IA · confirmado por Portería',
            default                                             => 'IA en el dispositivo',
        };
        if ($ev['tipo'] === 'ENTRADA') {
            $d[] = ['Vehículo', tipo_label($visita['tipo_vehiculo']) . ' · ' . $metodo];
            $d[] = ['Entrada', fecha($visita['entrada_en'], 'd/m/Y H:i')];
            $d[] = ['Visita', $visita['estado'] === 'EN_CURSO' ? 'En curso' : ucfirst(mb_strtolower($visita['estado']))];
            $d[] = ['Sin cobro hasta', $visita['liquidacion'] === 'EN_TOLERANCIA' && $visita['tolerancia_hasta']
                ? fecha($visita['tolerancia_hasta'], 'H:i') . ' (' . minutos_texto((int) config('tolerancia_minutos')) . ' de tolerancia)'
                : liquidacion_label($visita['liquidacion'])];
            return $d;
        }
        // SALIDA: la categoría registrada al entrar frente a la observada ahora, el tiempo, el cobro y su pago.
        $cobro = $visita['id_cobro'] !== null ? (new \App\Repositories\CobroRepository($this->pdo))->find((int) $visita['id_cobro']) : null;
        $d[] = ['Al entrar', tipo_label($visita['tipo_vehiculo']) . ' · ' . fecha($visita['entrada_en'], 'H:i')];
        $d[] = ['Al salir', ($ev['tipo_confirmado'] ? tipo_label($ev['tipo_confirmado']) : '—') . ' · ' . $metodo];
        $d[] = ['Tiempo', duracion($visita['entrada_en'], $visita['salida_en'])];
        $d[] = ['Cobro', match (true) {
            in_array($visita['liquidacion'], ['SALIDA_EN_TOLERANCIA', 'EN_TOLERANCIA'], true) => 'Sin cobro (dentro de la tolerancia)',
            $visita['liquidacion'] === 'NO_APLICA' => 'Sin cobro',
            $cobro !== null => money($cobro['valor']) . ' · ' . liquidacion_label($visita['liquidacion']),
            default => liquidacion_label($visita['liquidacion']),
        }];
        // Estadía de varios días: los días siguientes a la entrada tienen su propio cobro.
        $repo = new \App\Repositories\CobroRepository($this->pdo);
        $extras = array_values(array_filter($repo->fechasDeVisita((int) $visita['id_visita']), static fn ($f) => $f > substr($visita['entrada_en'], 0, 10)));
        $pendientes = $repo->pendientesDeVisita((int) $visita['id_visita']);
        if ($extras !== []) {
            $total = (float) $this->pdo->query('SELECT COALESCE(SUM(valor), 0) FROM cobros_diarios WHERE estado <> \'ANULADO\' AND id_visita = ' . (int) $visita['id_visita'])->fetchColumn();
            $d[] = ['Estadía', count($extras) + 1 . ' días · ' . money($total) . ' en total' . ($pendientes ? ' · ' . count($pendientes) . ' sin pagar' : '')];
        }
        if ($cobro !== null) {
            $d[] = ['Pago', match (true) {
                $pendientes !== [] => 'Pendiente',
                $cobro['estado'] === 'PAGADO' => 'Pagado' . ($cobro['medio_pago'] === 'SALDO' ? ' con saldo UniPark' : ($cobro['consecutivo'] ? ' · ' . $cobro['consecutivo'] : '')),
                $cobro['estado'] === 'ANULADO' => 'Cobro anulado',
                default   => 'Pendiente',
            }];
        }
        return $d;
    }

    /** Eventos sin resolver que superaron su tiempo: se cierran como EXPIRADO, sin registrar nada. */
    public function expirarVencidos(): int
    {
        $n = 0;
        foreach ($this->eventos->vencidos((int) config('eventos_expiran_min', 10), 3) as $id) {
            $this->pdo->beginTransaction();
            try {
                $ev = $this->eventos->find($id, true);
                if ($ev && in_array($ev['estado'], ['EN_CAPTURA', 'PENDIENTE_PORTERO'], true)) {
                    $this->expirar($ev, $ev['estado'] === 'PENDIENTE_PORTERO' ? 'SIN_ATENDER' : null, 'Sin resolver a tiempo. No se registró nada; la persona debe escanear de nuevo.', null);
                    $n++;
                }
                $this->pdo->commit();
            } catch (\Throwable $e) {
                $this->pdo->rollBack();
                throw $e;
            }
            $this->borrarCaptura($id);
        }
        return $n;
    }

    private function liberarPuesto(?array $captura, int $usuario): void
    {
        if ($captura !== null) {
            $this->expirar($captura, 'CAPTURA_REEMPLAZADA', 'Se escaneó otra identidad en el mismo punto de acceso.', $usuario);
        }
    }

    private function expirar(array $ev, ?string $motivo, string $mensaje, ?int $usuario): void
    {
        $this->eventos->cerrar((int) $ev['id_evento'], 'EXPIRADO', ['motivo' => $motivo, 'resultado' => 'EXPIRADO', 'mensaje' => $mensaje]);
        $this->audit->log('ACCESO_EXPIRADO', 'eventos_acceso', (int) $ev['id_evento'], 'EXITO', $motivo, [], $usuario);
    }

    public static function mensajeEntrada(?array $visita): string
    {
        if ($visita === null) {
            return 'Entrada registrada.';
        }
        return match ($visita['liquidacion']) {
            'NO_APLICA' => $visita['categoria'] === ReglasVisita::VISITANTE
                ? 'Entrada de visitante registrada. Sin cobro.'
                : tipo_label($visita['tipo_vehiculo']) . ': entrada registrada sin tarifa.',
            'CUBIERTA'  => 'Entrada registrada. Lo pagado hoy ya cubre este vehículo.',
            default     => sprintf('Entrada registrada. Tolerancia sin cobro hasta las %s.', fecha($visita['tolerancia_hasta'], 'H:i')),
        };
    }

    // ============================================================ Captura opcional para el portero

    public static function rutaCaptura(int $idEvento): string
    {
        return BASE_PATH . '/storage/capturas/' . $idEvento . '.jpg';
    }

    /** Solo si la configuración lo permite. JPEG de hasta 200 KB; se borra al resolver o expirar. */
    private function guardarCaptura(int $idEvento, ?string $dataUrl): bool
    {
        if (!(config('ia')['captura_portero'] ?? false) || $dataUrl === null || !str_starts_with($dataUrl, 'data:image/jpeg;base64,')) {
            return false;
        }
        $bin = base64_decode(substr($dataUrl, 23), true);
        if ($bin === false || strlen($bin) > 200 * 1024 || substr($bin, 0, 3) !== "\xFF\xD8\xFF") {
            return false;
        }
        $dir = dirname(self::rutaCaptura($idEvento));
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        return file_put_contents(self::rutaCaptura($idEvento), $bin, LOCK_EX) !== false;
    }

    private function borrarCaptura(int $idEvento): void
    {
        $ruta = self::rutaCaptura($idEvento);
        if (is_file($ruta)) {
            @unlink($ruta);
        }
    }
}
