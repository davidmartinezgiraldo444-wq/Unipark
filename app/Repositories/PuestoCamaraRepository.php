<?php
declare(strict_types=1);

namespace App\Repositories;

/** Última clase de vehículo detectada por el celular de cámara de cada puesto (solo el tipo y la hora). */
final class PuestoCamaraRepository extends Repository
{
    public function todos(): array
    {
        return $this->all('SELECT id_puesto, nombre FROM puestos_camara ORDER BY id_puesto');
    }

    public function existe(int $idPuesto): bool
    {
        return $this->one('SELECT 1 AS n FROM puestos_camara WHERE id_puesto = ?', [$idPuesto]) !== null;
    }

    public function actualizar(int $idPuesto, string $tipo, float $confianza, int $usuario, float $segunda = 0.0): int
    {
        return $this->run(
            'UPDATE puestos_camara SET tipo_detectado = ?, confianza = ?, segunda = ?, detectado_en = NOW(3), actualizado_por = ?
              WHERE id_puesto = ?',
            [$tipo, round(max(0, min(1, $confianza)), 4), round(max(0, min(1, $segunda)), 4), $usuario, $idPuesto]
        );
    }

    /** @return ?array{tipo_detectado:?string,confianza:?string,segunda:?string,detectado_en:?string,hace_ms:?int} */
    public function ultima(int $idPuesto): ?array
    {
        return $this->one(
            'SELECT id_puesto, nombre, tipo_detectado, confianza, segunda, detectado_en,
                    TIMESTAMPDIFF(MICROSECOND, detectado_en, NOW(3)) DIV 1000 AS hace_ms
               FROM puestos_camara WHERE id_puesto = ?',
            [$idPuesto]
        );
    }
}
