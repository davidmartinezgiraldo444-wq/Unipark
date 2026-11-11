<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Services\LectorDocumento;
use App\Services\ServicioAcceso;
use Tests\Support\TestCase;

/**
 * Identificación de personas por documento: formatos que llegan del escáner (Code 128 / QR / lector USB o
 * digitación = el mismo texto; PDF417 con estructura de cédula) y carnés ficticios de demostración.
 * La lectura por cámara y el OCR ocurren en el navegador: aquí se prueba lo que el SERVIDOR recibe.
 */
final class IdentificacionTest extends TestCase
{
    protected bool $usaBaseDeDatos = true;

    private function kiosco(): int
    {
        return (int) $this->valor("SELECT id_usuario FROM usuarios WHERE login = 'acceso1'");
    }

    public function testElPdf417DePruebaSeLeeComoUnaCedula(): void
    {
        $texto = LectorDocumento::cedulaDePrueba('1000000003', 'Gómez Duque', 'Mariana');
        $this->assertTrue(str_starts_with($texto, 'UNIPARK-FICTICIO'), 'Encabezado propio, no el de un documento real');
        $this->assertSame(['codigo' => '1000000003', 'nombre' => 'Mariana Gomez Duque', 'formato' => 'CEDULA_PDF417'], LectorDocumento::interpretar($texto));
    }

    public function testCadaFormatoIdentificaALaMismaPersona(): void
    {
        $s = new ServicioAcceso($this->db());
        // Code 128 / QR / lector USB / digitación: el escáner entrega el mismo texto (el código del carné).
        $a = $s->identificar('1000000003', '', 1, $this->kiosco());
        $this->assertSame('EN_CAPTURA', $a['estado']);
        $this->assertSame('Mariana Gómez Duque', $a['persona']['nombre']);
        $this->assertSame('••••••0003', $a['persona']['documento'], 'La pantalla solo ve el documento enmascarado');
        // PDF417 de prueba: reintento sobre el mismo evento (no se duplica).
        $b = $s->identificar(LectorDocumento::cedulaDePrueba('1000000003', 'Gómez Duque', 'Mariana'), '', 1, $this->kiosco());
        $this->assertSame($a['id'], $b['id'], 'Misma persona, mismo evento');
        // Formato inválido.
        $this->assertRegla('DOCUMENTO', fn () => $s->identificar('??', '', 1, $this->kiosco()));
    }

    public function testUnDocumentoNoRegistradoPasaAlPorteroComoVisitante(): void
    {
        $ev = (new ServicioAcceso($this->db()))->identificar('99887766', 'Ana Prueba', 1, $this->kiosco());
        $this->assertSame('PENDIENTE_PORTERO', $ev['estado']);
        $this->assertSame('IDENTIDAD_NO_REGISTRADA', $ev['motivo']);
        $this->assertSame('••••7766', $ev['persona']['documento']);
    }

    public function testElModoDiagnosticoDelEscanerSoloExisteEnLaDemo(): void
    {
        $this->assertSame('', (string) config('instancia'), 'Esta suite corre como sistema principal');
        $this->iniciarComo('acceso1');
        $html = $this->solicitud('GET', '/porteria/escaner')->body;
        $this->assertContains('data-manual', $html, 'El escáner carga');
        $this->assertNotContains('data-diagnostico', $html, 'Sin panel de diagnóstico en el sistema real');
        $this->assertNotContains('diagnostico.js', $html);
    }

    public function testLosCarnesDePruebaFueraDeLaDemoSoloLosVeAdministracion(): void
    {
        $this->assertSame('', (string) config('instancia'), 'Esta suite corre como sistema principal');
        $this->iniciarComo('porteria');
        $this->assertSame(404, $this->solicitud('GET', '/porteria/carnets')->status, 'Hallazgo B7');
        $this->iniciarComo('admin');
        $html = $this->solicitud('GET', '/porteria/carnets')->body;
        $this->assertContains('data-bcid="qrcode"', $html);
        $this->assertContains('data-bcid="pdf417"', $html);
        $this->assertContains('Carné ficticio', $html);
        $this->assertNotContains('>1000000003<', $html, 'Sin el número completo como texto visible');
    }
}
