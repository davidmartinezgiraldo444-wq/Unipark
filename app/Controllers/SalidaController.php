<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Request;
use App\Helpers\Response;
use App\Services\ReglaNegocioException;
use App\Services\ServicioSalida;

final class SalidaController extends Controller
{
    public function autorizar(Request $request): Response
    {
        $idVisita = $request->int('id_visita');
        $documento = $request->input('documento');
        if ($idVisita === null) {
            $this->flash('err', 'No se indicó la visita.');
            return $this->redirect('porteria');
        }

        try {
            $visita = (new ServicioSalida($this->db()))->autorizar($idVisita, $this->usuarioId());
            $this->flash('ok', sprintf(
                'Salida autorizada: %s. La visita quedó CERRADA.',
                $visita['nombres'] . ' ' . $visita['apellidos']
            ));
            return $this->redirect('porteria');
        } catch (ReglaNegocioException $e) {
            $this->flash('err', $e->getMessage());
            return $this->redirect('porteria?documento=' . rawurlencode($documento));
        }
    }
}
