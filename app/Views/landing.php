<?php /** Página pública de presentación. $conectado indica si hay sesión abierta. */ ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>UniPark · Parqueadero universitario</title>
  <meta name="description" content="UniPark controla la entrada, el pago y la salida del parqueadero universitario con tarifa fija por tipo de vehículo, roles definidos y trazabilidad completa.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(asset('css/styles.css')) ?>">
  <link rel="icon" href="<?= e(asset('img/logo.svg')) ?>" type="image/svg+xml">
</head>
<body>

  <header class="site-header">
    <div class="wrap header-inner">
      <a class="brand" href="<?= e(url('inicio')) ?>" aria-label="UniPark, inicio">
        <span class="brand-mark" aria-hidden="true">P</span>
        <span class="brand-name">UniPark</span>
      </a>
      <nav class="nav" aria-label="Principal">
        <a href="#flujo">Cómo funciona</a>
        <a href="#tarifa">Tarifa</a>
        <a href="#roles">Roles</a>
        <a href="#datos">Datos y seguridad</a>
      </nav>
      <a class="btn btn-ink btn-sm" href="<?= e(url($conectado ? 'panel' : 'login')) ?>">Iniciar sesión</a>
    </div>
  </header>

  <main>

    <!-- Hero -->
    <section class="hero">
      <div class="wrap hero-grid">
        <div class="hero-copy">
          <p class="eyebrow">Corporación Universitaria Lasallista · Parqueadero</p>
          <h1>Entrada, pago y salida.<br><span class="mark">En ese orden.</span></h1>
          <p class="lead">UniPark es el sistema que registra cada visita al parqueadero universitario. Portería registra la entrada, Caja confirma el pago y la salida solo se autoriza cuando ese pago existe.</p>
          <div class="hero-actions">
            <a class="btn btn-ink" href="<?= e(url($conectado ? 'panel' : 'login')) ?>">Ingresar al sistema</a>
            <a class="btn btn-line" href="#flujo">Ver el flujo</a>
          </div>
          <dl class="hero-facts">
            <div><dt>Tarifa</dt><dd>Fija por tipo</dd></div>
            <div><dt>Vehículos</dt><dd>Carro y moto</dd></div>
            <div><dt>Placa o foto</dt><dd>No se guarda</dd></div>
          </dl>
        </div>

        <!-- Simulador del flujo -->
        <div class="sim" aria-labelledby="sim-title">
          <div class="sim-head">
            <span id="sim-title">Prueba la regla</span>
            <button class="sim-reset" type="button" data-action="reiniciar">Reiniciar</button>
          </div>

          <div class="ticket">
            <div class="ticket-row">
              <span class="k">Estudiante</span>
              <span class="v mono">CC ••••• 4821</span>
            </div>
            <div class="ticket-row">
              <span class="k">Tipo</span>
              <span class="v">
                <span class="seg" role="radiogroup" aria-label="Tipo de vehículo">
                  <button type="button" role="radio" aria-checked="true" data-tipo="CARRO">Carro</button>
                  <button type="button" role="radio" aria-checked="false" data-tipo="MOTO">Moto</button>
                </span>
              </span>
            </div>
            <div class="ticket-row">
              <span class="k">Entrada</span>
              <span class="v mono" data-field="entrada">—</span>
            </div>
            <div class="ticket-row">
              <span class="k">Pago</span>
              <span class="v mono" data-field="pago">—</span>
            </div>
            <div class="ticket-row">
              <span class="k">Salida</span>
              <span class="v mono" data-field="salida">—</span>
            </div>
            <div class="ticket-row ticket-state">
              <span class="k">Estado</span>
              <span class="badge" data-field="estado">SIN VISITA</span>
            </div>
          </div>

          <div class="sim-actions">
            <button class="act" type="button" data-action="entrada"><span class="act-role">Portería</span>Registrar entrada</button>
            <button class="act" type="button" data-action="pago"><span class="act-role">Caja</span>Registrar pago</button>
            <button class="act" type="button" data-action="salida"><span class="act-role">Portería</span>Autorizar salida</button>
          </div>

          <p class="sim-msg" role="status" aria-live="polite" data-field="mensaje">Intenta autorizar la salida antes de pagar.</p>
        </div>
      </div>
    </section>

    <!-- Flujo -->
    <section class="section" id="flujo">
      <div class="wrap">
        <header class="section-head">
          <p class="eyebrow">Cómo funciona</p>
          <h2>Tres pasos, tres responsables.</h2>
          <p>Cada paso lo ejecuta un rol distinto y queda registrado con fecha, hora y funcionario. Ningún paso se puede saltar.</p>
        </header>

        <ol class="steps">
          <li class="step">
            <span class="step-n mono">01</span>
            <span class="step-role">Portería</span>
            <h3>Registra la entrada</h3>
            <p>Busca a la persona por documento y selecciona carro o moto. El sistema verifica que no tenga otra visita abierta y guarda la tarifa vigente de su categoría: estudiante o profesor.</p>
            <span class="step-out mono">Visita EN_CURSO</span>
          </li>
          <li class="step">
            <span class="step-n mono">02</span>
            <span class="step-role">Caja</span>
            <h3>Confirma el pago</h3>
            <p>Caja ve las visitas pendientes, cobra el valor exacto y registra el medio de pago. Se emite un comprobante con consecutivo único. Una visita, un pago.</p>
            <span class="step-out mono">Pago PAGADO</span>
          </li>
          <li class="step">
            <span class="step-n mono">03</span>
            <span class="step-role">Portería</span>
            <h3>Autoriza la salida</h3>
            <p>El sistema comprueba que la visita esté abierta y pagada. Si falta el pago, la salida no se registra y la persona es remitida a Caja.</p>
            <span class="step-out mono">Visita CERRADA</span>
          </li>
        </ol>

        <div class="notice">
          <span class="notice-tag mono">RN-08</span>
          <p>Sin pago confirmado el sistema responde: <strong>“Pago pendiente. Diríjase a Caja.”</strong> La hora de salida no se guarda.</p>
        </div>
      </div>
    </section>

    <!-- Tarifa -->
    <section class="section section-alt" id="tarifa">
      <div class="wrap tarifa-grid">
        <header class="section-head">
          <p class="eyebrow">Tarifa</p>
          <h2>Un valor fijo. Sin cobro por horas.</h2>
          <p>El valor depende del vehículo y de si eres estudiante o profesor. El tiempo de permanencia no cambia lo que se paga, y cada visita conserva el valor aplicado al entrar.</p>
        </header>

        <div class="tarifas">
          <?php foreach (['CARRO', 'MOTO'] as $tipo): ?>
            <div class="tarifa">
              <span class="tarifa-tipo mono"><?= e($tipo) ?></span>
              <?php foreach (['ESTUDIANTE' => 'Estudiante', 'PROFESOR' => 'Profesor'] as $cat => $etiqueta):
                  $t = $tarifas[$tipo][$cat] ?? null; ?>
                <div class="tarifa-fila">
                  <span class="tarifa-nota"><?= e($etiqueta) ?></span>
                  <span class="tarifa-valor"><?= $t ? e(money($t['valor'])) : '—' ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
          <ul class="tarifa-list">
            <li>Sin fracciones, multas, reservas ni mensualidades.</li>
            <li>Cada cambio de tarifa registra quién lo hizo y desde cuándo rige.</li>
            <li>El tiempo transcurrido se muestra como información, nunca como cobro.</li>
          </ul>
        </div>
      </div>
    </section>

    <!-- Roles -->
    <section class="section" id="roles">
      <div class="wrap">
        <header class="section-head">
          <p class="eyebrow">Roles y permisos</p>
          <h2>Cada quien hace lo suyo.</h2>
          <p>Los permisos se verifican en el servidor en cada solicitud. Ocultar un botón no protege una operación; el sistema deniega por defecto.</p>
        </header>

        <div class="roles">
          <article class="role">
            <h3>Portería</h3>
            <p>Busca estudiantes y profesores, registra entradas y autoriza salidas después de la validación automática del pago.</p>
            <p class="role-no">No registra pagos ni cambia tarifas.</p>
          </article>
          <article class="role">
            <h3>Caja</h3>
            <p>Consulta visitas pendientes, registra el pago y emite el comprobante. Genera su cierre de caja por turno.</p>
            <p class="role-no">No registra entradas ni salidas.</p>
          </article>
          <article class="role">
            <h3>Administrador</h3>
            <p>Gestiona usuarios, roles, estudiantes, profesores y tarifas. Consulta reportes y la bitácora de auditoría.</p>
            <p class="role-no">No modifica pagos o visitas en silencio.</p>
          </article>
          <article class="role">
            <h3>Auditor</h3>
            <p>Consulta reportes, pagos, visitas y la bitácora autorizada.</p>
            <p class="role-no">Solo lectura. No crea ni modifica nada.</p>
          </article>
        </div>

        <div class="matrix-wrap">
          <table class="matrix">
            <caption>Matriz de permisos</caption>
            <thead>
              <tr>
                <th scope="col">Acción</th>
                <th scope="col">Portería</th>
                <th scope="col">Caja</th>
                <th scope="col">Admin.</th>
                <th scope="col">Auditor</th>
              </tr>
            </thead>
            <tbody>
              <tr><th scope="row">Registrar entrada</th><td class="y">Sí</td><td>—</td><td class="p">Corrección</td><td>—</td></tr>
              <tr><th scope="row">Registrar pago</th><td>—</td><td class="y">Sí</td><td class="p">Anulación</td><td>—</td></tr>
              <tr><th scope="row">Autorizar salida</th><td class="y">Con pago</td><td>—</td><td>—</td><td>—</td></tr>
              <tr><th scope="row">Administrar tarifas</th><td>—</td><td>—</td><td class="y">Sí</td><td>—</td></tr>
              <tr><th scope="row">Usuarios y roles</th><td>—</td><td>—</td><td class="y">Sí</td><td>—</td></tr>
              <tr><th scope="row">Reportes</th><td class="p">Limitados</td><td class="p">Autorizados</td><td class="y">Sí</td><td class="y">Sí</td></tr>
              <tr><th scope="row">Ver auditoría</th><td>—</td><td>—</td><td class="y">Sí</td><td class="y">Sí</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- Reglas -->
    <section class="section section-alt" id="reglas">
      <div class="wrap">
        <header class="section-head">
          <p class="eyebrow">Reglas del sistema</p>
          <h2>Lo que UniPark no permite.</h2>
          <p>Estas reglas se validan en la aplicación y se refuerzan en la base de datos con transacciones, índices únicos y claves foráneas.</p>
        </header>

        <ul class="rules">
          <li><span class="mono">RN-07</span><p>Dos visitas abiertas para la misma persona, ni siquiera con solicitudes simultáneas.</p></li>
          <li><span class="mono">RN-06</span><p>Una salida sin pago confirmado para esa misma visita.</p></li>
          <li><span class="mono">RN-09</span><p>Un segundo pago válido sobre una visita ya pagada.</p></li>
          <li><span class="mono">RN-05</span><p>Que Portería marque una visita como pagada.</p></li>
          <li><span class="mono">RN-11</span><p>Que un cambio de tarifa altere visitas ya registradas.</p></li>
          <li><span class="mono">RN-12</span><p>Borrar pagos o visitas. Toda corrección es una anulación con motivo y auditoría.</p></li>
        </ul>
      </div>
    </section>

    <!-- Datos y seguridad -->
    <section class="section" id="datos">
      <div class="wrap">
        <header class="section-head">
          <p class="eyebrow">Datos y seguridad</p>
          <h2>Solo lo necesario. Todo trazable.</h2>
          <p>El sistema recolecta los datos mínimos para operar y registra quién hizo qué, cuándo y con qué resultado.</p>
        </header>

        <div class="data-grid">
          <div class="data-col">
            <h3>Se registra</h3>
            <ul>
              <li>Documento, nombres, apellidos y categoría (estudiante o profesor)</li>
              <li>Tipo de vehículo por visita: carro o moto</li>
              <li>Hora de entrada, pago y salida</li>
              <li>Funcionario responsable de cada paso</li>
              <li>Tarifa aplicada y consecutivo del recibo</li>
            </ul>
          </div>
          <div class="data-col data-col-no">
            <h3>No se registra</h3>
            <ul>
              <li>Placa del vehículo</li>
              <li>Marca, modelo o color</li>
              <li>Fotografías o video</li>
              <li>Datos de tarjetas bancarias</li>
              <li>Contraseñas en texto plano</li>
            </ul>
          </div>
        </div>

        <ul class="security">
          <li><h4>Cuentas individuales</h4><p>Sin credenciales compartidas. Contraseñas con hash y sesiones que expiran por inactividad.</p></li>
          <li><h4>Protección web</h4><p>Consultas preparadas, escape de HTML y token anti-CSRF en cada operación sensible.</p></li>
          <li><h4>Auditoría</h4><p>Cambios de tarifa, pagos, anulaciones y salidas denegadas quedan en la bitácora.</p></li>
          <li><h4>Respaldos</h4><p>Copias de seguridad automáticas con pruebas periódicas de restauración.</p></li>
        </ul>

        <p class="legal">El tratamiento de datos personales se ajusta a la política institucional y a la Ley 1581 de 2012.</p>
      </div>
    </section>

    <!-- CTA -->
    <section class="cta">
      <div class="wrap cta-inner">
        <div>
          <h2>Acceso para personal autorizado.</h2>
          <p>Portería, Caja, Administración y Auditoría ingresan con su cuenta individual.</p>
        </div>
        <a class="btn btn-yellow" href="<?= e(url($conectado ? 'panel' : 'login')) ?>">Iniciar sesión</a>
      </div>
    </section>

  </main>

  <footer class="site-footer">
    <div class="wrap footer-inner">
      <div class="brand brand-sm">
        <span class="brand-mark" aria-hidden="true">P</span>
        <span class="brand-name">UniPark</span>
      </div>
      <p>Sistema de control de acceso y operación del parqueadero universitario · Corporación Universitaria Lasallista</p>
      <p class="mono">© 2026</p>
    </div>
  </footer>

  <script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
