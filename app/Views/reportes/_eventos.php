<?php /** Eventos de acceso: identidad, detección, confirmación y resultado. */ ?>
<div class="table-wrap">
  <table class="table">
    <thead><tr><th>Fecha</th><th>Evento</th><th>Persona</th><th>IA detectó</th><th>Confirmado</th><th>Resultado</th></tr></thead>
    <tbody>
    <?php foreach ($filas as $ev): ?>
      <tr>
        <td class="nowrap"><?= e(fecha($ev['creado_en'], 'd/m/Y H:i:s')) ?><span class="sub">#<?= e($ev['id_evento']) ?></span></td>
        <td><?= $ev['tipo'] === 'SALIDA' ? 'Salida' : 'Ingreso' ?><span class="sub"><?= e($ev['estado']) ?><?= $ev['motivo'] ? ' · ' . e($ev['motivo']) : '' ?></span></td>
        <td><?= $ev['id_estudiante'] ? e($ev['nombres'] . ' ' . $ev['apellidos']) : 'No registrada' ?><span class="sub mono"><?= e(mask_doc((string) ($ev['documento'] ?? $ev['documento_leido']))) ?></span></td>
        <td><?= $ev['tipo_detectado'] ? e(tipo_label($ev['tipo_detectado'])) : '—' ?><?php if ($ev['confianza'] !== null): ?><span class="sub"><?= e((int) round((float) $ev['confianza'] * 100)) ?> % · <?= e($ev['fuente']) ?></span><?php endif; ?></td>
        <td><?= $ev['tipo_confirmado'] ? e(tipo_label($ev['tipo_confirmado'])) : '—' ?><span class="sub"><?= $ev['metodo'] === 'MANUAL' ? 'Portero: ' . e($ev['portero']) : e($ev['metodo'] ?? '') ?></span></td>
        <td><?= e($ev['resultado'] ?? '') ?><span class="sub"><?= e($ev['mensaje'] ?? '') ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
