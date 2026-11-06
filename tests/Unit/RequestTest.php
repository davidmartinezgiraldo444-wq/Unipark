<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\Request;
use Tests\Support\TestCase;

/** IP del visitante y HTTPS detrás de un túnel (ngrok, Cloudflare Tunnel). */
final class RequestTest extends TestCase
{
    private array $servidor = [];

    public function setUp(): void
    {
        parent::setUp();
        $this->servidor = $_SERVER;
    }

    public function tearDown(): void
    {
        $_SERVER = $this->servidor;
        parent::tearDown();
    }

    private function servidor(array $valores): void
    {
        foreach (['REMOTE_ADDR', 'HTTP_X_FORWARDED_FOR', 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_PROTO', 'HTTPS', 'SERVER_PORT'] as $k) {
            unset($_SERVER[$k]);
        }
        $_SERVER = $valores + $_SERVER;
    }

    public function testDetrasDeUnTunelSeUsaLaIpDelVisitante(): void
    {
        $this->servidor(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => '181.50.10.7, 10.0.0.1']);
        $this->assertSame('181.50.10.7', Request::ipCliente());

        $this->servidor(['REMOTE_ADDR' => '::1', 'HTTP_CF_CONNECTING_IP' => '2800:e2:1::5']);
        $this->assertSame('2800:e2:1::5', Request::ipCliente(), 'Cloudflare');
    }

    public function testUnVisitanteDirectoNoPuedeFalsificarSuIp(): void
    {
        $this->servidor(['REMOTE_ADDR' => '190.1.2.3', 'HTTP_X_FORWARDED_FOR' => '8.8.8.8']);
        $this->assertSame('190.1.2.3', Request::ipCliente(), 'Solo se confía en la cabecera si la conexión es local');

        $this->servidor(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => "' OR 1=1"]);
        $this->assertSame('127.0.0.1', Request::ipCliente(), 'Una cabecera inválida se ignora');
    }

    public function testHttpsTerminadoPorElTunel(): void
    {
        $this->servidor(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_PROTO' => 'https']);
        $this->assertTrue(Request::esHttps());

        $this->servidor(['REMOTE_ADDR' => '190.1.2.3', 'HTTP_X_FORWARDED_PROTO' => 'https']);
        $this->assertFalse(Request::esHttps(), 'Desde fuera, la cabecera no basta');

        $this->servidor(['REMOTE_ADDR' => '127.0.0.1']);
        $this->assertFalse(Request::esHttps(), 'HTTP local');
    }
}
