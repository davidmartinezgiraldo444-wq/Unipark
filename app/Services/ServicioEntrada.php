<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Repositories\CobroRepository;
use App\Repositories\EstudianteRepository;
use App\Repositories\PagoRepository;
use App\Repositories\TarifaRepository;
use App\Repositories\VisitaRepository;
use PDO;
use PDOException;

/** Entrada en portería (sección 7.1) y anulación de visitas (sección 7.4). */
final class ServicioEntrada
{
    private EstudianteRepository $estudiantes;
    private VisitaRepository $visitas;
    private TarifaRepository $tarifas;
    private PagoRepository $pagos;
    private AuditService $audit;

    public function __construct(private PDO $pdo)
    {
        $this->estudiantes = new EstudianteRepository($pdo);
        $this->visitas = new VisitaRepository($pdo);
        $this->tarifas = new TarifaRepository($pdo);
        $this->pagos = new PagoRepository($pdo);
        $this->audit = new AuditService($pdo);
    }

    /**
     * Registra la entrada. El tipo de vehículo llega de la cámara (IA) o de la clasificación manual del portero.
     * Copia en la visita la tarifa diaria vigente para su categoría y vehículo (RN-11) y arranca la tolerancia.
     * Bloquea la fila de la persona para serializar entradas simultáneas (RN-13);
     * el índice único visita_abierta es la segunda barrera (no aplica a visitantes).
     * @throws ReglaNegocioException
     */
    public function registrar(int $idEstudiante, string $tipo, int $usuario, string $origen = 'MANUAL', ?int $idEvento = null): array
    {
        $this->pdo->beginTransaction();
        try {
            $id = $this->registrarEnTransaccion($idEstudiante, $tipo, $usuario, $origen, $idEvento);
            $this->pdo->commit();
        } catch (ReglaNegocioException $e) {
            $this->pdo->rollBack();
            if ($e->codigo === 'VISITA_ABIERTA') {
                $this->rechazoDuplicado($idEstudiante, $usuario, $e->datos['id_visita'] ?? null);
            }
            throw $e;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
        return $this->visitas->find($id);
    }

    /**
     * Igual que registrar(), pero dentro de una transacción abierta por quien llama (evento de acceso).
     * Devuelve el id de la visita. La tarifa, la categoría y la tolerancia salen siempre de la base de datos:
     * el navegador solo aporta el tipo de vehículo ya validado por el servidor.
     * @throws ReglaNegocioException
     */
    public function registrarEnTransaccion(int $idEstudiante, string $tipo, int $usuario, string $origen = 'MANUAL', ?int $idEvento = null): int
    {
        if (!ReglasVisita::tipoValido($tipo) || !in_array($tipo, (array) config('tipos_habilitados', ReglasVisita::TIPOS), true)) {
            throw new ReglaNegocioException('TIPO_INVALIDO', 'Indica un tipo de vehículo habilitado: ' . implode(', ', array_map('tipo_label', (array) config('tipos_habilitados'))) . '.');
        }
        $origen = $origen === 'IA' ? 'IA' : 'MANUAL';

        try {
            $estudiante = $this->estudiantes->find($idEstudiante, true);
            if ($estudiante === null || (int) $estudiante['activo'] !== 1) {
                throw new ReglaNegocioException(
                    'ESTUDIANTE_NO_DISPONIBLE',
                    'La persona no está registrada o está inactiva. Remítela a Administración.'
                );
            }
            $categoria = $estudiante['categoria'];

            // Los visitantes pueden tener varias visitas abiertas: no se valida ni se advierte (requerimiento 6).
            if ($categoria !== ReglasVisita::VISITANTE) {
                $abierta = $this->visitas->abiertaDeEstudiante($idEstudiante, true);
                if ($abierta !== null) {
                    throw new ReglaNegocioException(
                        'VISITA_ABIERTA',
                        'La persona ya tiene una visita abierta. No se registró una nueva entrada.',
                        ['id_visita' => (int) $abierta['id_visita']]
                    );
                }
            }

            // Tarifa diaria según categoría y vehículo; bicicleta, patineta y visitantes no tienen tarifa.
            $tarifa = null;
            if (ReglasCobro::generaCobro($categoria, $tipo)) {
                $tarifa = $this->tarifas->vigente($tipo, $categoria);
                if ($tarifa === null) {
                    throw new ReglaNegocioException(
                        'SIN_TARIFA',
                        'No hay una tarifa vigente para ' . tipo_label($tipo) . ' · ' . categoria_label($categoria) . '. Comunícate con el Administrador.'
                    );
                }
            }

            // Si lo ya cobrado hoy cubre la tarifa de este vehículo, la visita queda cubierta. Si es de tarifa
            // mayor, espera su tolerancia: al vencer se cobra solo la diferencia.
            $repoCobros = new CobroRepository($this->pdo);
            $cobroHoy = $tarifa ? $repoCobros->delDia($idEstudiante, $this->hoy(), true) : null;
            $totalHoy = $cobroHoy ? $repoCobros->totalDelDia($idEstudiante, $this->hoy(), true) : null;
            $liquidacion = ReglasCobro::liquidacionInicial($categoria, $tipo, $totalHoy, $tarifa['valor'] ?? null);

            $id = $this->visitas->create($idEstudiante, [
                'tipo'        => $tipo,
                'origen'      => $origen,
                'categoria'   => $categoria,
                'id_tarifa'   => $tarifa ? (int) $tarifa['id_tarifa'] : null,
                'tarifa'      => $tarifa['valor'] ?? null,
                'liquidacion' => $liquidacion,
                'id_cobro'    => $liquidacion === 'CUBIERTA' ? (int) $cobroHoy['id_cobro'] : null,
                'id_evento'   => $idEvento,
            ], $usuario);
            $this->audit->log('ENTRADA_REGISTRADA', 'visitas', $id, 'EXITO', null, [
                'tipo'            => $tipo,
                'origen'          => $origen,
                'categoria'       => $categoria,
                'id_tarifa'       => $tarifa ? (int) $tarifa['id_tarifa'] : null,
                'tarifa_aplicada' => $tarifa['valor'] ?? null,
                'liquidacion'     => $liquidacion,
                'id_evento'       => $idEvento,
            ], $usuario);
            return $id;
        } catch (PDOException $e) {
            if (Database::isDuplicate($e)) {
                throw new ReglaNegocioException(
                    'VISITA_ABIERTA',
                    'La persona ya tiene una visita abierta. No se registró una nueva entrada.'
                );
            }
            throw $e;
        }
    }

    /**
     * Documento que no está registrado: se clasifica como visitante y entra sin cobro.
     * No se considera estudiante, profesor ni trabajador por presentar un documento.
     * @throws ReglaNegocioException
     */
    public function registrarVisitante(string $documento, string $nombre, string $tipo, int $usuario, string $origen = 'MANUAL'): array
    {
        $documento = strtoupper(preg_replace('/[\s.]/', '', $documento));
        if (!\App\Helpers\Validator::esDocumento($documento)) {
            throw new ReglaNegocioException('DOCUMENTO', 'El documento leído no es válido (5 a 20 letras, números o guiones).');
        }
        $persona = $this->estudiantes->findByCodigo($documento);
        if ($persona === null) {
            try {
                $idPersona = $this->estudiantes->crearVisitante($documento, trim(preg_replace('/\s+/u', ' ', $nombre)));
                $this->audit->log('VISITANTE_REGISTRADO', 'estudiantes', $idPersona, 'EXITO', null, [], $usuario);
            } catch (PDOException $e) {
                if (!Database::isDuplicate($e)) {
                    throw $e;
                }
                $idPersona = (int) $this->estudiantes->findByDocumento($documento)['id_estudiante'];
            }
        } else {
            $idPersona = (int) $persona['id_estudiante'];
        }
        return $this->registrar($idPersona, $tipo, $usuario, $origen);
    }

    /**
     * Anulación excepcional de una visita abierta, con motivo (RN-12). Nunca borra la fila.
     * Si la visita tiene un pago válido, primero debe anularse el pago.
     * @throws ReglaNegocioException
     */
    public function anular(int $idVisita, int $usuario, string $motivo): void
    {
        $motivo = trim($motivo);
        $min = (int) config('motivo_min_longitud');
        if (mb_strlen($motivo) < $min) {
            throw new ReglaNegocioException('MOTIVO', "Describe el motivo de la anulación (mínimo {$min} caracteres).");
        }

        $this->pdo->beginTransaction();
        try {
            $visita = $this->visitas->find($idVisita, true);
            if ($visita === null || $visita['estado'] !== 'EN_CURSO') {
                throw new ReglaNegocioException('VISITA_NO_ABIERTA', 'Solo se puede anular una visita en curso.');
            }
            if ($this->pagos->validoDeVisita($idVisita, true) !== null) {
                throw new ReglaNegocioException('VISITA_PAGADA', 'La visita tiene un pago confirmado. Anula primero el pago.');
            }
            // COBRADA (cobro del día) y DIFERENCIA (vehículo de tarifa mayor) generaron un cobro propio de esta
            // visita: anularla dejaría ese cobro vivo y sin visita (error E2 de la auditoría).
            if (in_array($visita['liquidacion'], ['COBRADA', 'DIFERENCIA'], true) && $visita['id_cobro'] !== null) {
                $cobro = (new CobroRepository($this->pdo))->find((int) $visita['id_cobro'], true);
                if ($cobro !== null && $cobro['estado'] !== 'ANULADO') {
                    throw new ReglaNegocioException('VISITA_COBRADA', 'Esta visita generó un cobro (del día o por diferencia). Anula primero el cobro (y su pago, si lo tiene).');
                }
            }
            $this->visitas->anular($idVisita, $usuario, $motivo);
            $this->audit->log('VISITA_ANULADA', 'visitas', $idVisita, 'EXITO', $motivo, [], $usuario);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function hoy(): string
    {
        return (string) $this->pdo->query('SELECT CURDATE()')->fetchColumn();
    }

    private function rechazoDuplicado(int $idEstudiante, int $usuario, ?int $idVisita): void
    {
        $this->audit->log('ENTRADA_RECHAZADA', 'visitas', $idVisita, 'DENEGADO', 'El estudiante ya tiene una visita abierta', [
            'id_estudiante' => $idEstudiante,
        ], $usuario);
    }
}
