<?php /** Calibración de la IA con datos reales de la portería (portero como referencia). */ ?>
<h2 class="section-title">Calibración de la IA</h2>
<p class="muted mb-12">Por rango de confianza: cuántos eventos registró la IA sola y cuántos resolvió el portero, y si el portero confirmó o corrigió la sugerencia. Úsalo para ajustar <span class="mono">IA_UMBRAL</span> con evidencia de la propia portería.</p>
<?php if (!$calibracion): ?>
<p class="empty">Todavía no hay eventos con detección de la IA para calibrar.</p>
<?php else: ?>
<div class="table-wrap">
  <table class="table">
    <thead><tr><th>Confianza desde</th><th class="num">Eventos</th><th class="num">Automáticos (IA)</th><th class="num">Portero confirmó</th><th class="num">Portero corrigió</th></tr></thead>
    <tbody>
    <?php foreach ($calibracion as $c): ?>
      <tr>
        <td class="mono"><?= e((int) round((float) $c['desde'] * 100)) ?> %</td>
        <td class="num"><?= e($c['eventos']) ?></td>
        <td class="num"><?= e($c['automaticas']) ?></td>
        <td class="num"><?= e($c['manual_coincide']) ?></td>
        <td class="num"><?= (int) $c['manual_corrige'] > 0 ? '<span class="text-err">' . e($c['manual_corrige']) . '</span>' : '0' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
