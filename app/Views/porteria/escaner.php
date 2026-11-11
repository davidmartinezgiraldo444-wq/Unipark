<?php
/**
 * Punto de acceso (celular o tableta de la portería). La persona escanea su carné o cédula; la cámara
 * clasifica el vehículo. Esta pantalla NO permite elegir el tipo de vehículo: si la IA no está segura, el
 * evento pasa al panel del portero. Lógica en assets/js/escaner.js.
 */
?>
<div class="modo" role="group" aria-label="Configuración del punto de acceso">
  <label class="sr-only" for="modo-vehiculo">Cómo se reconoce el vehículo</label>
  <select id="modo-vehiculo" data-modo>
    <?php foreach ($puestos as $p): ?>
      <option value="uno:<?= e($p['id_puesto']) ?>">Este celular · <?= e($p['nombre']) ?></option>
      <option value="puesto:<?= e($p['id_puesto']) ?>">Cámara del puesto · <?= e($p['nombre']) ?></option>
    <?php endforeach; ?>
  </select>
  <label class="sr-only" for="camara">Cámara</label>
  <select id="camara" data-camara hidden></select>
</div>

<div class="visor" data-visor data-fase="carnet">
  <video data-video playsinline muted autoplay></video>
  <div class="visor-marco" aria-hidden="true"><span class="visor-linea"></span></div>
  <span class="visor-estado" data-visor-estado>Cámara apagada</span>
  <p class="visor-texto" data-visor-texto>Escanea el código de barras de tu carné o cédula</p>
  <div class="visor-apagado" data-visor-apagado>
    <p>La cámara se usa solo en este equipo. Por defecto no se guardan ni se envían imágenes.</p>
    <button class="btn btn-yellow" type="button" data-encender>Encender cámara</button>
  </div>
</div>

<div class="tarjetas">
  <section class="tarjeta" data-paso="buscar">
    <form class="manual" data-manual autocomplete="off">
      <label class="sr-only" for="codigo-manual">Documento o código del carné</label>
      <input id="codigo-manual" name="codigo" inputmode="numeric" maxlength="40" placeholder="Documento o código del carné" required>
      <button class="btn btn-ink" type="submit">Continuar</button>
    </form>
    <div class="fila mt-12">
      <label class="btn btn-line btn-sm" for="foto-carnet">Tomar foto del carné</label>
      <input id="foto-carnet" class="sr-only" type="file" accept="image/*" capture="environment" data-foto>
      <button class="btn btn-line btn-sm" type="button" data-ocr>Leer número impreso (OCR)</button>
      <input class="sr-only" type="file" accept="image/*" capture="environment" data-ocr-foto aria-label="Foto del documento para leer el texto">
    </div>
    <p class="lectura" data-lectura role="status" aria-live="polite" hidden></p>
    <p class="field-hint mt-8" data-consejo>Pon el código de barras horizontal dentro del marco, a 15–25 cm, con buena luz. También funciona un lector USB/Bluetooth de códigos de barras en este campo.</p>
  </section>

  <section class="tarjeta" data-paso="evento" hidden>
    <div class="fila-entre">
      <div>
        <p class="ia-fuente" data-ev-tipo>Ingreso</p>
        <h2 data-p-nombre></h2>
        <p class="sub"><span data-p-categoria class="cat-tag"></span> <span class="mono" data-p-documento></span></p>
      </div>
      <span class="estado-chip" data-ev-chip>Identidad validada</span>
    </div>
    <p class="aviso mt-14" data-p-aviso hidden></p>
    <div class="ia mt-14" data-ia>
      <span class="ia-fuente" data-ia-fuente>Reconocimiento del vehículo</span>
      <div class="fila-entre">
        <span class="ia-clase" data-ia-clase>Buscando vehículo…</span>
        <span class="mono" data-ia-confianza></span>
      </div>
      <div class="ia-barra"><span data-ia-barra></span></div>
      <p class="ia-nota" data-ia-nota>Ubica el vehículo de lado frente a la cámara. Solo se registra su categoría.</p>
    </div>
  </section>
</div>

<div class="veredicto" data-veredicto hidden role="alert" aria-live="assertive">
  <strong data-v-titulo></strong>
  <p data-v-texto></p>
  <span class="cuenta-regresiva" data-v-extra></span>
  <dl class="v-detalle" data-v-detalle></dl>
  <p class="v-resolver" data-v-resolver hidden>Para salir: la persona recarga su saldo en <strong>Mi saldo</strong> desde el celular (el cobro pendiente se paga solo) o paga en <strong>Caja</strong>. Después vuelve a escanear su carné.</p>
  <button class="btn" type="button" data-v-cerrar>Siguiente</button>
</div>

<?php if (!empty($diagnostico)): ?>
  <?php /* Solo en la demo. Lo calcula el navegador (assets/js/diagnostico.js); nada de esto se envía ni se guarda. */ ?>
  <details class="diagnostico" data-diagnostico>
    <summary>Diagnóstico del dispositivo (demo)</summary>
    <dl>
      <dt>Contexto seguro (HTTPS)</dt><dd data-diag="seguro">—</dd>
      <dt>Dirección</dt><dd data-diag="origen">—</dd>
      <dt>Navegador</dt><dd data-diag="navegador">—</dd>
      <dt>Cámara</dt><dd data-diag="camara">Sin encender</dd>
      <dt>Lector de códigos</dt><dd data-diag="lector">—</dd>
      <dt>Último formato leído</dt><dd data-diag="formato">—</dd>
      <dt>Modelo de IA</dt><dd data-diag="modelo">Sin cargar</dd>
      <dt>Última inferencia</dt><dd data-diag="ia">—</dd>
      <dt>Tiempo de inferencia</dt><dd data-diag="ms">—</dd>
    </dl>
    <p class="field-hint">El contenido de los códigos no se muestra: solo su formato y longitud.</p>
  </details>
<?php endif; ?>
