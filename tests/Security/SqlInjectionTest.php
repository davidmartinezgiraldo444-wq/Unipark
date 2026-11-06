<?php
declare(strict_types=1);

namespace Tests\Security;

use App\Repositories\EstudianteRepository;
use App\Repositories\UsuarioRepository;
use App\Services\AuthService;
use Tests\Support\TestCase;

/** CP-11: el contenido malicioso se trata como dato y no altera la consulta. XSS: se escapa al imprimir. */
final class SqlInjectionTest extends TestCase
{
    protected bool $usaBaseDeDatos = true;

    private const CARGAS = [
        "' OR '1'='1",
        "1000000001' --",
        "'; DROP TABLE pagos; --",
        '" OR ""="',
        "%' OR 1=1 #",
        '1000000001 UNION SELECT password_hash FROM usuarios',
    ];

    public function testCP11BusquedaDeEstudiantesTrataLaEntradaComoDato(): void
    {
        $repo = new EstudianteRepository($this->db());
        foreach (self::CARGAS as $carga) {
            $this->assertSame(null, $repo->findByDocumento($carga), "findByDocumento: {$carga}");
            $this->assertSame([], $repo->search($carga, 25, 0), "search: {$carga}");
        }
        $this->assertSame(12, $repo->count(''), 'La tabla sigue intacta');
        $this->assertSame(7, (int) $this->valor('SELECT COUNT(*) FROM pagos'), 'La tabla pagos sigue existiendo');
    }

    public function testCP11ComodinesDeLikeSeBuscanLiteralmente(): void
    {
        $repo = new EstudianteRepository($this->db());
        $this->assertSame(0, $repo->count('%'), 'El % no debe coincidir con todo');
        $this->assertSame(0, $repo->count('_'), 'El _ no debe coincidir con cualquier carácter');
        $this->assertSame(1, $repo->count('Valentina'));
    }

    public function testCP11LoginConInyeccionNoAutentica(): void
    {
        $auth = new AuthService($this->db());
        foreach (self::CARGAS as $carga) {
            $this->assertRegla('CREDENCIALES', fn () => $auth->intentar($carga, $carga, '10.1.1.' . random_int(1, 250)));
        }
        $this->assertSame(null, (new UsuarioRepository($this->db()))->findByLogin("admin' --"));
    }

    public function testCP11PorteriaYReportesConParametrosMaliciosos(): void
    {
        $this->iniciarComo('admin');
        $r = $this->solicitud('GET', '/estudiantes?buscar=' . rawurlencode("' OR '1'='1"));
        $this->assertSame(200, $r->status);
        $this->assertContains('No se encontraron personas', $r->body);

        $r = $this->solicitud('GET', '/reportes?tab=visitas&tipo=' . rawurlencode("CARRO' OR '1'='1") . '&usuario=' . rawurlencode('1 OR 1=1'));
        $this->assertSame(200, $r->status, 'Los filtros inválidos se descartan sin error');

        $this->iniciarComo('porteria');
        $r = $this->solicitud('GET', '/porteria?documento=' . rawurlencode("' OR '1'='1"));
        $this->assertSame(200, $r->status);
        $this->assertContains('Escribe un documento válido', $r->body);
    }

    public function testXssLosDatosSeEscapanAntesDeImprimirse(): void
    {
        $this->db()->exec("INSERT INTO estudiantes (documento, nombres, apellidos) VALUES ('5000000001', '<script>alert(1)</script>', '\"><img src=x onerror=alert(2)>')");
        $this->iniciarComo('admin');
        $html = $this->solicitud('GET', '/estudiantes?buscar=5000000001')->body;

        $this->assertNotContains('<script>alert(1)</script>', $html);
        $this->assertNotContains('<img src=x', $html);
        $this->assertContains('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    public function testErroresInternosNoSeMuestranEnPantalla(): void
    {
        $this->iniciarComo('admin');
        // Una tabla eliminada provoca una excepción de base de datos dentro del controlador.
        \Tests\Support\TestDatabase::admin()->exec('RENAME TABLE tarifas TO tarifas_tmp');
        try {
            $r = $this->solicitud('GET', '/tarifas');
        } finally {
            \Tests\Support\TestDatabase::admin()->exec('RENAME TABLE tarifas_tmp TO tarifas');
        }
        $this->assertSame(500, $r->status);
        $this->assertNotContains('SQLSTATE', $r->body, 'Sin trazas técnicas (RF-18)');
        $this->assertNotContains('tarifas', strtolower(strip_tags(explode('<main', $r->body)[1] ?? '')), 'Sin nombres internos');
    }
}
