<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Services\ReglasVisita;

final class TarifaRepository extends Repository
{
    /** Tarifa que rige en este momento para el tipo de vehículo y la categoría indicados. */
    public function vigente(string $tipo, string $categoria): ?array
    {
        return $this->one(
            'SELECT * FROM tarifas
              WHERE tipo_vehiculo = ? AND categoria = ? AND activa = 1
                AND vigente_desde <= NOW()
                AND (vigente_hasta IS NULL OR vigente_hasta > NOW())
              ORDER BY vigente_desde DESC
              LIMIT 1',
            [$tipo, $categoria]
        );
    }

    /** @return array<string,array<string,?array>> tarifa vigente por tipo y categoría: $t['CARRO']['PROFESOR'] */
    public function vigentes(): array
    {
        $r = [];
        foreach (ReglasVisita::TIPOS as $tipo) {
            foreach (ReglasVisita::CATEGORIAS as $categoria) {
                $r[$tipo][$categoria] = $this->vigente($tipo, $categoria);
            }
        }
        return $r;
    }

    public function abierta(string $tipo, string $categoria): ?array
    {
        return $this->one(
            'SELECT * FROM tarifas WHERE tarifa_abierta = ? FOR UPDATE',
            [$tipo . '-' . $categoria]
        );
    }

    public function cerrar(int $id, string $hasta): void
    {
        $this->run('UPDATE tarifas SET vigente_hasta = ? WHERE id_tarifa = ?', [$hasta, $id]);
    }

    public function create(string $tipo, string $categoria, string $valor, string $desde, int $usuario): int
    {
        return $this->insert(
            'INSERT INTO tarifas (tipo_vehiculo, categoria, valor, vigente_desde, creado_por) VALUES (?, ?, ?, ?, ?)',
            [$tipo, $categoria, $valor, $desde, $usuario]
        );
    }

    public function historial(): array
    {
        return $this->all(
            'SELECT t.*, u.nombre AS creado_por_nombre,
                    (SELECT COUNT(*) FROM visitas v WHERE v.id_tarifa = t.id_tarifa) AS visitas
               FROM tarifas t
               JOIN usuarios u ON u.id_usuario = t.creado_por
              ORDER BY t.vigente_desde DESC, t.tipo_vehiculo, t.categoria'
        );
    }
}
