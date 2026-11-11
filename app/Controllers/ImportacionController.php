<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Request;
use App\Helpers\Response;
use App\Services\ReglaNegocioException;
use App\Services\ServicioImportacion;

/** Importación de la base institucional de personas (solo Administrador: PERSONA_IMPORTAR). */
final class ImportacionController extends Controller
{
    public function index(Request $request): Response
    {
        $servicio = new ServicioImportacion($this->db());
        $ver = $request->int('ver');
        return $this->view('estudiantes/importar', [
            'titulo'    => 'Importar base institucional',
            'historial' => $servicio->historial(),
            'columnas'  => config('importacion')['columnas'],
            'ver'       => $ver,
            'errores'   => $ver ? $servicio->errores($ver) : [],
        ]);
    }

    public function procesar(Request $request): Response
    {
        $archivo = $_FILES['archivo'] ?? null;
        $modo = $request->input('modo') === 'APLICAR' ? 'APLICAR' : 'VALIDAR';
        if (!is_array($archivo) || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
            $this->flash('err', 'Selecciona el archivo CSV de la base institucional.');
            return $this->redirect('estudiantes/importar');
        }
        if (!preg_match('/\.(csv|txt)$/i', (string) $archivo['name'])) {
            $this->flash('err', 'El archivo debe ser CSV (exportado desde Excel como «CSV UTF-8» o separado por punto y coma).');
            return $this->redirect('estudiantes/importar');
        }
        try {
            $r = (new ServicioImportacion($this->db()))->procesar($archivo['tmp_name'], (string) $archivo['name'], $modo, $this->usuarioId());
        } catch (ReglaNegocioException $e) {
            $this->flash('err', $e->getMessage());
            return $this->redirect('estudiantes/importar');
        } finally {
            @unlink($archivo['tmp_name']);
        }
        $resumen = sprintf('%d filas · %d nuevas · %d actualizadas · %d sin cambios · %d rechazadas.', $r['filas'], $r['creadas'], $r['actualizadas'], $r['sin_cambios'], $r['rechazadas']);
        $this->flash($r['rechazadas'] > 0 ? 'err' : 'ok', ($modo === 'APLICAR' ? 'Importación aplicada: ' : 'Validación (no se guardó ninguna persona): ') . $resumen);
        return $this->redirect('estudiantes/importar' . ($r['rechazadas'] > 0 ? '?ver=' . $r['id_importacion'] : ''));
    }
}
