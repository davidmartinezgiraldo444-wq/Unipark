<?php /** Segundo celular: reconoce el vehículo del carril y publica solo su tipo. Lógica en assets/js/camara-vehiculos.js. */ ?>
<div class="modo" role="group" aria-label="Puesto de cámara">
  <label class="sr-only" for="puesto">Puesto</label>
  <select id="puesto" data-puesto>
    <?php foreach ($puestos as $p): ?>
      <option value="<?= e($p['id_puesto']) ?>"><?= e($p['nombre']) ?></option>
    <?php endforeach; ?>
  </select>
  <span>Fija el celular apuntando al carril de entrada.</span>
</div>

<div class="visor" data-visor data-fase="vehiculo">
  <video data-video playsinline muted autoplay></video>
  <div class="visor-marco" aria-hidden="true"></div>
  <span class="visor-estado" data-visor-estado>Cámara apagada</span>
  <p class="visor-texto" data-visor-texto>Reconocimiento continuo del vehículo</p>
  <div class="visor-apagado" data-visor-apagado>
    <p>Las imágenes se analizan en este celular y nunca se envían: al servidor solo llega el tipo de vehículo.</p>
    <button class="btn btn-yellow" type="button" data-encender>Encender cámara</button>
  </div>
</div>

<div class="tarjetas">
  <section class="tarjeta puesto-grande">
    <div class="ia" data-ia>
      <span class="ia-fuente" data-ia-fuente>Modelo</span>
      <span class="ia-clase" data-ia-clase>—</span>
      <div class="ia-barra"><span data-ia-barra></span></div>
      <p class="ia-nota" data-ia-nota>El celular del portero usa esta detección cuando escanea un carnet.</p>
    </div>
  </section>
</div>
