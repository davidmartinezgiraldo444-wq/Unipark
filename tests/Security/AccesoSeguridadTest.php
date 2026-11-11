<?php
declare(strict_types=1);

namespace Tests\Security;

use App\Helpers\Csrf;
use App\Helpers\WebPush;
use Tests\Support\TestCase;

/**
 * Seguridad del punto de acceso y de las API por el mismo recorrido que la web (Kernel → CSRF → sesión →
 * permiso → controlador). Demuestra que el navegador no puede fijar el tipo de vehículo, la tarifa, el
 * tiempo ni el saldo, y que cada rol solo hace lo suyo.
 */
final class AccesoSeguridadTest extends TestCase
{
    protected bool $usaBaseDeDatos = true;

    private function json(string $metodo, string $ruta, array $datos = []): array
    {
        $r = $this->solicitud($metodo, $ruta, $datos);
        return ['status' => $r->status, 'body' => json_decode($r->body, true) ?? []];
    }

    private function eventoPendiente(): int
    {
        $this->iniciarComo('acceso1');
        $ev = $this->json('POST', '/api/acceso/identificar', ['codigo' => '1000000001', 'puesto' => '1'])['body'];
        $this->json('POST', '/api/acceso/' . $ev['id'] . '/clasificacion', ['fuente' => 'CELULAR', 'tipo' => 'CARRO', 'confianza' => '0.40', 'segunda' => '0.30', 'final' => '1']);
        return (int) $ev['id'];
    }

    public function testUnEstudianteNoPuedeClasificarNiResolverNiRegistrarEntradas(): void
    {
        $id = $this->eventoPendiente();
        $this->iniciarComo('estudiante1');
        $this->assertSame(403, $this->json('POST', '/api/acceso/identificar', ['codigo' => '1000000001'])['status']);
        $this->assertSame(403, $this->json('POST', "/api/acceso/$id/clasificacion", ['tipo' => 'BICICLETA', 'confianza' => '1'])['status']);
        $this->assertSame(403, $this->json('POST', "/api/porteria/eventos/$id/resolver", ['accion' => 'CLASIFICAR', 'tipo' => 'BICICLETA'])['status'], 'No puede invocar la selección manual');
        $this->assertSame(403, $this->json('GET', '/api/porteria/eventos')['status'], 'No ve los avisos del portero');
        $this->assertSame(403, $this->json('POST', '/api/porteria/entrada', ['id_estudiante' => '1', 'tipo' => 'BICICLETA'])['status']);
        $this->assertSame(403, $this->json('POST', '/api/cobros/procesar')['status'], 'Ya no dispara cobros');
        $this->assertSame('PENDIENTE_PORTERO', $this->valor('SELECT estado FROM eventos_acceso WHERE id_evento = ?', [$id]));
    }

    public function testElPuntoDeAccesoNoPuedeElegirElTipoNiResolver(): void
    {
        $id = $this->eventoPendiente();
        $this->assertSame(403, $this->json('POST', "/api/porteria/eventos/$id/resolver", ['accion' => 'CLASIFICAR', 'tipo' => 'MOTO'])['status']);
        $this->assertSame(403, $this->json('POST', '/api/porteria/entrada', ['id_estudiante' => '2', 'tipo' => 'MOTO'])['status'], 'Sin entrada manual');
        $this->assertSame(403, $this->solicitud('GET', '/porteria')->status);
    }

    public function testUnPorteroAutorizadoResuelveYUnoSinRolNo(): void
    {
        $id = $this->eventoPendiente();
        $this->iniciarComo('caja');
        $this->assertSame(403, $this->json('POST', "/api/porteria/eventos/$id/resolver", ['accion' => 'CLASIFICAR', 'tipo' => 'MOTO'])['status']);

        $this->iniciarComo('porteria');
        $avisos = $this->json('GET', '/api/porteria/eventos')['body'];
        $this->assertSame(1, count($avisos['eventos']));
        $this->assertSame('••••••0001', $avisos['eventos'][0]['documento'], 'El panel no expone el documento completo');
        $r = $this->json('POST', "/api/porteria/eventos/$id/resolver", ['accion' => 'CLASIFICAR', 'tipo' => 'MOTO']);
        $this->assertSame(200, $r['status']);
        $this->assertSame('CONFIRMADO', $r['body']['estado']);
        $this->assertSame([], $this->json('GET', '/api/porteria/eventos')['body']['eventos'], 'El aviso desaparece al resolverse');
    }

    public function testElNavegadorNoFijaTarifaTiempoNiSaldo(): void
    {
        $this->iniciarComo('acceso1');
        $ev = $this->json('POST', '/api/acceso/identificar', ['codigo' => '1000000001', 'puesto' => '1'])['body'];
        $this->json('POST', '/api/acceso/' . $ev['id'] . '/clasificacion', [
            'fuente' => 'CELULAR', 'tipo' => 'CARRO', 'confianza' => '0.95', 'segunda' => '0.01',
            // Campos manipulados: el servidor los ignora.
            'tarifa' => '1', 'valor' => '1', 'tarifa_aplicada' => '1', 'tolerancia_hasta' => '2099-01-01 00:00:00',
            'saldo' => '999999', 'categoria' => 'VISITANTE', 'id_estudiante' => '2',
        ]);
        // El portero confirma la sugerencia de la IA (regla de Portería): tampoco puede fijar tarifa, tiempo ni saldo.
        $this->iniciarComo('porteria');
        $this->json('POST', '/api/porteria/eventos/' . $ev['id'] . '/resolver', ['accion' => 'CLASIFICAR', 'tipo' => 'CARRO', 'tarifa' => '1', 'saldo' => '999999']);
        $v = $this->fila('SELECT * FROM visitas WHERE id_evento_entrada = ?', [$ev['id']]);
        $this->assertSame('1', $v['id_estudiante'], 'La persona es la del evento, no la enviada');
        $this->assertSame('ESTUDIANTE', $v['categoria']);
        $this->assertSame('2000.00', $v['tarifa_aplicada'], 'Tarifa del servidor');
        $this->assertSame(900, strtotime($v['tolerancia_hasta']) - strtotime($v['entrada_en']), 'Tolerancia del servidor');
        $this->assertSame('10000.00', $this->valor('SELECT saldo FROM estudiantes WHERE id_estudiante = 1'));
    }

    public function testApiSinSesionResponde401JsonSinRegistrarCsrf(): void
    {
        $_SESSION = [];
        $antes = (int) $this->valor("SELECT COUNT(*) FROM auditoria WHERE accion = 'CSRF_INVALIDO'");
        for ($i = 0; $i < 5; $i++) {
            $r = $this->json('POST', '/api/cobros/procesar', ['_csrf' => 'viejo']);
            $this->assertSame(401, $r['status']);
            $this->assertSame('SESION_EXPIRADA', $r['body']['codigo'] ?? null, 'Respuesta JSON coherente');
        }
        $this->assertSame($antes, (int) $this->valor("SELECT COUNT(*) FROM auditoria WHERE accion = 'CSRF_INVALIDO'"), 'Los latidos de una sesión vencida no llenan la auditoría');

        $this->iniciarComo('porteria');
        $r = $this->solicitud('POST', '/api/cobros/procesar', ['_csrf' => 'falso'], false);
        $this->assertSame(419, $r->status);
        $this->assertSame('CSRF', json_decode($r->body, true)['codigo'] ?? null, 'CSRF inválido con sesión: JSON 419');
        $this->assertSame($antes + 1, (int) $this->valor("SELECT COUNT(*) FROM auditoria WHERE accion = 'CSRF_INVALIDO'"));
        $this->assertSame(404, $this->json('GET', '/api/no-existe')['status']);
    }

    public function testSesionDelDispositivoDuraElTurnoYLaDelEstudianteNo(): void
    {
        $id = $this->iniciarComo('acceso2');
        $_SESSION['ultima_actividad'] = time() - 3 * 3600; // 3 horas sin interacción humana
        $this->assertSame(200, $this->json('GET', '/api/camara/ultima?puesto=1')['status'], 'Celular de cámara sigue conectado');
        $this->assertSame(200, $this->json('POST', '/api/camara/deteccion', ['puesto' => '1', 'tipo' => 'CARRO', 'confianza' => '0.9', 'segunda' => '0.05'])['status']);

        $this->iniciarComo('estudiante1');
        $_SESSION['ultima_actividad'] = time() - 3 * 3600;
        $this->assertSame(303, $this->solicitud('GET', '/mi-cuenta')->status, 'La sesión del estudiante expira a los 15 min');
    }

    public function testSuscripcionPushSoloAServiciosConocidos(): void
    {
        $this->iniciarComo('estudiante1');
        $claves = ['p256dh' => WebPush::b64(random_bytes(65)), 'auth' => WebPush::b64(random_bytes(16))];
        foreach (['https://127.0.0.1/push', 'https://169.254.169.254/latest', 'http://fcm.googleapis.com/x', 'https://fcm.googleapis.com.evil.test/x', 'https://evil.test/fcm.googleapis.com'] as $url) {
            $this->assertSame(422, $this->json('POST', '/api/push/suscribir', ['endpoint' => $url] + $claves)['status'], $url);
        }
        $this->assertSame(200, $this->json('POST', '/api/push/suscribir', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123'] + $claves)['status']);
        $this->assertTrue(WebPush::servicioPermitido('https://web.push.apple.com/QK'));
        $this->assertFalse(WebPush::servicioPermitido('https://push.apple.com.evil.test/'));
    }

    public function testCronPorUrlDeshabilitadoSinFichaLarga(): void
    {
        $_SESSION = [];
        $this->assertSame(403, $this->solicitud('GET', '/cron/cobros?token=')->status);
        $this->assertSame(403, $this->solicitud('GET', '/cron/cobros?token=corta')->status);
    }

    public function testRolesYPermisosNuevos(): void
    {
        $esperado = [
            'Portería'        => ['ACCESO_KIOSCO', 'CLASIFICACION_RESOLVER', 'COBRO_PROCESAR'],
            'Caja'            => ['COBRO_PROCESAR'],
            'Administrador'   => ['COBRO_ANULAR', 'COBRO_PROCESAR', 'PERSONA_IMPORTAR', 'SALDO_REVERSAR'],
            'Punto de acceso' => ['ACCESO_KIOSCO'],
        ];
        foreach ($esperado as $rol => $permisos) {
            $real = $this->db()->prepare("SELECT p.codigo FROM rol_permiso rp JOIN roles r USING(id_rol) JOIN permisos p USING(id_permiso) WHERE r.nombre = ?");
            $real->execute([$rol]);
            $codigos = $real->fetchAll(\PDO::FETCH_COLUMN);
            foreach ($permisos as $p) {
                $this->assertTrue(in_array($p, $codigos, true), "$rol debe tener $p");
            }
        }
        $kiosco = $this->db()->query("SELECT COUNT(*) FROM rol_permiso rp JOIN roles r USING(id_rol) WHERE r.nombre = 'Punto de acceso'")->fetchColumn();
        $this->assertSame('1', (string) $kiosco, 'El punto de acceso solo tiene ACCESO_KIOSCO');
    }
}
