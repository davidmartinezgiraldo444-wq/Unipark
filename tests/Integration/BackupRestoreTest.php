<?php
declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\TestCase;
use Tests\Support\TestDatabase;

/** CP-14: la copia de seguridad se restaura con el procedimiento documentado (scripts/backup.php y restore.php). */
final class BackupRestoreTest extends TestCase
{
    protected bool $usaBaseDeDatos = true;

    private array $archivos = [];

    public function tearDown(): void
    {
        foreach ($this->archivos as $archivo) {
            @unlink($archivo);
        }
        foreach (glob(BASE_PATH . '/storage/backups/' . TestDatabase::nombre() . '_antes_de_restaurar_*.sql') as $previo) {
            @unlink($previo);
        }
        parent::tearDown();
    }

    public function testCP14RespaldoYRestauracionRecuperanElEstado(): void
    {
        $base = TestDatabase::nombre();
        $respaldo = BASE_PATH . '/storage/backups/prueba_cp14_' . uniqid() . '.sql';
        $this->archivos[] = $respaldo;

        [$codigo, $salida] = $this->php('scripts/backup.php', ["--db={$base}", "--out={$respaldo}"]);
        if ($codigo !== 0 && str_contains($salida, 'No se pudo ejecutar')) {
            $this->skip('mysqldump no está disponible; define MYSQL_BIN_DIR en .env');
        }
        $this->assertSame(0, $codigo, "backup.php: {$salida}");
        // mysqldump escribe los disparadores como /*!50003 CREATE*/ ... TRIGGER nombre.
        $this->assertContains('TRIGGER tr_visitas_inmutables', (string) file_get_contents($respaldo), 'El respaldo incluye los disparadores');

        $antes = $this->conteos();
        $consecutivo = $this->valor("SELECT ultimo FROM consecutivos WHERE nombre = 'RECIBO'");

        // Cambios posteriores al respaldo: deben desaparecer al restaurar.
        $this->db()->exec("INSERT INTO estudiantes (documento, nombres, apellidos) VALUES ('2000000001', 'Temporal', 'Prueba')");
        (new \App\Services\ServicioPago($this->db()))->registrar(8, 'EFECTIVO', 3);
        $this->assertTrue($this->conteos() !== $antes);

        [$codigo, $salida] = $this->php('scripts/restore.php', [$respaldo, "--db={$base}", '--yes']);
        $this->assertSame(0, $codigo, "restore.php: {$salida}");
        $this->assertContains('Respaldo de seguridad del estado actual', $salida, 'Crea un respaldo previo antes de restaurar');

        \App\Helpers\Database::set(null);
        $this->assertSame($antes, $this->conteos(), 'Los conteos vuelven al estado del respaldo');
        $this->assertSame($consecutivo, $this->valor("SELECT ultimo FROM consecutivos WHERE nombre = 'RECIBO'"));
        $this->assertSame(0, (int) $this->valor("SELECT COUNT(*) FROM estudiantes WHERE documento = '2000000001'"));

        // Las restricciones críticas siguen activas después de restaurar.
        $this->assertSqlFalla(
            fn () => $this->db()->exec("INSERT INTO visitas (id_estudiante, tipo_vehiculo, categoria, id_tarifa, tarifa_aplicada, entrada_por) VALUES (5, 'MOTO', 'ESTUDIANTE', 3, 1050, 2)"),
            'La visita abierta única sigue vigente tras restaurar'
        );
        $this->assertSqlFalla(
            fn () => TestDatabase::admin()->exec('DELETE FROM pagos WHERE id_pago = 1'),
            'Los disparadores siguen vigentes tras restaurar'
        );
    }

    public function testRestaurarSinConfirmacionNoModificaNada(): void
    {
        $base = TestDatabase::nombre();
        $respaldo = BASE_PATH . '/storage/backups/prueba_cp14_' . uniqid() . '.sql';
        $this->archivos[] = $respaldo;
        [$codigo, $salida] = $this->php('scripts/backup.php', ["--db={$base}", "--out={$respaldo}"]);
        if ($codigo !== 0) {
            $this->skip('mysqldump no está disponible');
        }
        $this->db()->exec("INSERT INTO estudiantes (documento, nombres, apellidos) VALUES ('2000000002', 'Se', 'Conserva')");

        [$codigo, $salida] = $this->php('scripts/restore.php', [$respaldo, "--db={$base}"], "otra_base\n");
        $this->assertSame(1, $codigo, 'Sin escribir el nombre correcto, se cancela');
        $this->assertContains('Cancelado', $salida);
        $this->assertSame(1, (int) $this->valor("SELECT COUNT(*) FROM estudiantes WHERE documento = '2000000002'"));
    }

    /** @return array{0:int,1:string} */
    private function php(string $script, array $args, string $entrada = ''): array
    {
        $proc = proc_open(
            array_merge([PHP_BINARY, BASE_PATH . '/' . $script], $args),
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        fwrite($pipes[0], $entrada);
        fclose($pipes[0]);
        $salida = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($proc), $salida];
    }

    private function conteos(): array
    {
        $r = [];
        foreach (['estudiantes', 'visitas', 'pagos', 'tarifas', 'usuarios'] as $t) {
            $r[$t] = (int) $this->valor("SELECT COUNT(*) FROM {$t}");
        }
        return $r;
    }
}
