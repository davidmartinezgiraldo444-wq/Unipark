<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Helpers\View;
use Tests\Support\TestCase;

/** Las pantallas muestran los datos que reciben (regresión: la variable $datos chocaba con View). */
final class VistasTest extends TestCase
{
    protected bool $usaBaseDeDatos = true;

    public function testEditarPersonaMuestraSusDatosYCategoria(): void
    {
        $this->iniciarComo('admin');
        $html = $this->solicitud('GET', '/estudiantes/6/editar')->body;
        $this->assertContains('value="1000000006"', $html, 'Documento');
        $this->assertContains('value="Samuel"', $html, 'Nombres');
        $this->assertContains('<option value="PROFESOR" selected>', $html, 'Categoría seleccionada');
    }

    public function testEditarUsuarioMuestraSusDatos(): void
    {
        $this->iniciarComo('admin');
        $html = $this->solicitud('GET', '/usuarios/2/editar')->body;
        $this->assertContains('value="Pedro Portero"', $html);
        $this->assertContains('<option value="1" selected>', $html, 'Rol Portería seleccionado');
    }

    public function testPorteriaMuestraLaTarifaDeLaCategoria(): void
    {
        $this->iniciarComo('porteria');
        $profesor = $this->solicitud('GET', '/porteria?documento=1000000011')->body;
        $this->assertContains('$ 3.000', $profesor, 'Carro profesor');
        $this->assertContains('$ 2.000', $profesor, 'Moto profesor');
        $estudiante = $this->solicitud('GET', '/porteria?documento=1000000009')->body;
        $this->assertContains('$ 2.000', $estudiante, 'Carro estudiante');
        $this->assertContains('$ 1.000', $estudiante, 'Moto estudiante');
    }

    public function testLaRaizAbreElAccesoOElPanel(): void
    {
        $r = $this->solicitud('GET', '/');
        $this->assertSame(303, $r->status);
        $this->assertSame('/login', $r->headers['Location'] ?? null, 'Sin sesión: inicio de sesión');

        $this->iniciarComo('caja');
        $this->assertSame('/panel', $this->solicitud('GET', '/')->headers['Location'] ?? null, 'Con sesión: panel');
    }

    public function testLaPresentacionPublicaQuedaEnInicio(): void
    {
        $r = $this->solicitud('GET', '/inicio');
        $this->assertSame(200, $r->status);
        $this->assertContains('Entrada, pago y salida.', $r->body);
        $this->assertContains('$ 3.000', $r->body, 'Muestra las tarifas vigentes');
    }

    public function testLosRecursosLlevanVersionParaEvitarCacheVieja(): void
    {
        $html = $this->solicitud('GET', '/login')->body;
        $this->assertTrue(preg_match('#assets/css/layout\.css\?v=\d+#', $html) === 1, 'CSS con ?v=');
        $this->assertTrue(preg_match('#assets/js/app\.js\?v=\d+#', $html) === 1, 'JS con ?v=');
    }

    public function testUnaVistaPuedeRecibirUnaVariableLlamadaDatos(): void
    {
        $html = View::render('install/form', [
            'datos' => ['host' => 'h', 'port' => '1', 'base' => 'b_prueba', 'usuario' => 'u', 'crear_cuenta' => true, 'cuenta_app' => 'a', 'demo' => false, 'sobrescribir' => false],
            'error' => null, 'requisitos' => [], 'cumple' => true,
        ], null);
        $this->assertContains('value="b_prueba"', $html);
    }
}
