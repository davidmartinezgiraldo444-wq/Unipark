-- =====================================================================
-- UniPark · Esquema de base de datos
-- Compatible con MariaDB 10.4+ y MySQL 8.0.16+ (InnoDB, utf8mb4).
--
-- Se aplica sobre una base ya seleccionada:
--   mysql -u root unipark_db < database/schema.sql
-- o con el instalador web (instalar.php) o php scripts/install.php
--
-- ATENCIÓN: elimina y recrea todas las tablas de UniPark.
-- Después se cargan database/base.sql (obligatorio) y database/seed.sql (demostración).
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS notificaciones_pasarela;
DROP TABLE IF EXISTS recargas_en_linea;
DROP TABLE IF EXISTS importaciones;
DROP TABLE IF EXISTS eventos_acceso;
DROP TABLE IF EXISTS migraciones;
DROP TABLE IF EXISTS puestos_camara;
DROP TABLE IF EXISTS push_suscripciones;
DROP TABLE IF EXISTS notificaciones;
DROP TABLE IF EXISTS movimientos_saldo;
DROP TABLE IF EXISTS auditoria;
DROP TABLE IF EXISTS pagos;
DROP TABLE IF EXISTS consecutivos;
DROP TABLE IF EXISTS cobros_diarios;
DROP TABLE IF EXISTS visitas;
DROP TABLE IF EXISTS tarifas;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS rol_permiso;
DROP TABLE IF EXISTS permisos;
DROP TABLE IF EXISTS roles;
DROP TABLE IF EXISTS estudiantes;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- Personas que usan el parqueadero: estudiantes, profesores, trabajadores
-- y visitantes (RN-01, RF-04). Solo los datos personales necesarios.
-- La categoría define la tarifa; los visitantes no pagan.
-- codigo_carnet: código de barras del carnet cuando no es el documento.
-- saldo: saldo vigente para el débito automático (libro en movimientos_saldo).
-- ---------------------------------------------------------------------
CREATE TABLE estudiantes (
    id_estudiante  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    documento      VARCHAR(20)   NOT NULL,
    codigo_carnet  VARCHAR(40)   NULL,
    nombres        VARCHAR(80)   NOT NULL,
    apellidos      VARCHAR(80)   NOT NULL,
    correo         VARCHAR(120)  NULL,
    telefono       VARCHAR(20)   NULL,
    categoria      ENUM('ESTUDIANTE', 'PROFESOR', 'TRABAJADOR', 'VISITANTE') NOT NULL DEFAULT 'ESTUDIANTE',
    saldo          DECIMAL(10, 2) NOT NULL DEFAULT 0,
    activo         TINYINT(1)    NOT NULL DEFAULT 1,
    creado_en      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_estudiante),
    CONSTRAINT uq_estudiantes_documento UNIQUE (documento),
    CONSTRAINT uq_estudiantes_carnet    UNIQUE (codigo_carnet),
    CONSTRAINT ck_estudiantes_documento CHECK (CHAR_LENGTH(documento) BETWEEN 5 AND 20),
    CONSTRAINT ck_estudiantes_activo    CHECK (activo IN (0, 1)),
    CONSTRAINT ck_estudiantes_saldo     CHECK (saldo >= 0),
    KEY ix_estudiantes_nombre (apellidos, nombres)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Roles, permisos y su relación muchos a muchos (RBAC, sección 4).
-- ---------------------------------------------------------------------
CREATE TABLE roles (
    id_rol      TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre      VARCHAR(40)      NOT NULL,
    descripcion VARCHAR(200)     NOT NULL,
    activo      TINYINT(1)       NOT NULL DEFAULT 1,
    PRIMARY KEY (id_rol),
    CONSTRAINT uq_roles_nombre UNIQUE (nombre),
    CONSTRAINT ck_roles_activo CHECK (activo IN (0, 1))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE permisos (
    id_permiso  SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo      VARCHAR(40)       NOT NULL,
    descripcion VARCHAR(200)      NOT NULL,
    PRIMARY KEY (id_permiso),
    CONSTRAINT uq_permisos_codigo UNIQUE (codigo)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE rol_permiso (
    id_rol     TINYINT UNSIGNED  NOT NULL,
    id_permiso SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (id_rol, id_permiso),
    KEY ix_rol_permiso_permiso (id_permiso),
    CONSTRAINT fk_rol_permiso_rol     FOREIGN KEY (id_rol)     REFERENCES roles (id_rol),
    CONSTRAINT fk_rol_permiso_permiso FOREIGN KEY (id_permiso) REFERENCES permisos (id_permiso)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Usuarios: cuentas individuales, contraseña solo como hash (RNF-01).
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
    id_usuario         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    nombre             VARCHAR(120)     NOT NULL,
    login              VARCHAR(120)     NOT NULL,
    password_hash      VARCHAR(255)     NOT NULL,
    id_rol             TINYINT UNSIGNED NOT NULL,
    -- Persona vinculada (roles Estudiante, Profesor y Trabajador): su saldo, cobros y avisos.
    id_estudiante      INT UNSIGNED     NULL,
    activo             TINYINT(1)       NOT NULL DEFAULT 1,
    debe_cambiar_clave TINYINT(1)       NOT NULL DEFAULT 1,
    ultimo_acceso      DATETIME         NULL,
    creado_en          DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_usuario),
    CONSTRAINT uq_usuarios_login UNIQUE (login),
    CONSTRAINT uq_usuarios_estudiante UNIQUE (id_estudiante),
    KEY ix_usuarios_rol (id_rol),
    CONSTRAINT fk_usuarios_rol FOREIGN KEY (id_rol) REFERENCES roles (id_rol),
    CONSTRAINT fk_usuarios_estudiante FOREIGN KEY (id_estudiante) REFERENCES estudiantes (id_estudiante),
    CONSTRAINT ck_usuarios_activo CHECK (activo IN (0, 1)),
    CONSTRAINT ck_usuarios_cambio CHECK (debe_cambiar_clave IN (0, 1)),
    CONSTRAINT ck_usuarios_hash   CHECK (CHAR_LENGTH(password_hash) >= 50)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tarifas con historial de vigencia (RN-03, RF-06).
-- Valor por DÍA según tipo de vehículo (carro o moto) y categoría
-- (estudiante, profesor o trabajador). Bicicleta, patineta y visitantes
-- no tienen tarifa. Las ENUM son iguales a las de visitas y cobros_diarios
-- porque se relacionan por llave foránea compuesta; los CHECK limitan los valores.
-- Solo una tarifa abierta (sin vigente_hasta) por combinación tipo + categoría.
-- ---------------------------------------------------------------------
CREATE TABLE tarifas (
    id_tarifa      INT UNSIGNED           NOT NULL AUTO_INCREMENT,
    tipo_vehiculo  ENUM('CARRO', 'MOTO', 'BICICLETA', 'PATINETA') NOT NULL,
    categoria      ENUM('ESTUDIANTE', 'PROFESOR', 'TRABAJADOR', 'VISITANTE') NOT NULL,
    valor          DECIMAL(10, 2)         NOT NULL,
    vigente_desde  DATETIME               NOT NULL,
    vigente_hasta  DATETIME               NULL,
    activa         TINYINT(1)             NOT NULL DEFAULT 1,
    creado_por     INT UNSIGNED           NOT NULL,
    creado_en      DATETIME               NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tarifa_abierta VARCHAR(20) AS (IF(vigente_hasta IS NULL AND activa = 1, CONCAT(tipo_vehiculo, '-', categoria), NULL)) STORED,
    PRIMARY KEY (id_tarifa),
    CONSTRAINT uq_tarifas_abierta UNIQUE (tarifa_abierta),
    CONSTRAINT uq_tarifas_id_tipo UNIQUE (id_tarifa, tipo_vehiculo, categoria),
    KEY ix_tarifas_vigencia (tipo_vehiculo, categoria, vigente_desde),
    KEY ix_tarifas_creado_por (creado_por),
    CONSTRAINT fk_tarifas_usuario FOREIGN KEY (creado_por) REFERENCES usuarios (id_usuario),
    CONSTRAINT ck_tarifas_valor   CHECK (valor >= 0),
    CONSTRAINT ck_tarifas_rango   CHECK (vigente_hasta IS NULL OR vigente_hasta >= vigente_desde),
    CONSTRAINT ck_tarifas_activa  CHECK (activa IN (0, 1)),
    CONSTRAINT ck_tarifas_tipo      CHECK (tipo_vehiculo IN ('CARRO', 'MOTO')),
    CONSTRAINT ck_tarifas_categoria CHECK (categoria IN ('ESTUDIANTE', 'PROFESOR', 'TRABAJADOR'))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Visitas: una fila por estancia (entrada → salida). Del vehículo solo se
-- guarda su categoría (tipo_vehiculo) y si la definió la IA o el portero:
-- sin placa, marca, color ni fotografías (RN-02).
-- tolerancia_hasta: fin de los 15 minutos sin cobro desde la entrada.
-- liquidacion: resultado de la regla de cobro diario de esta visita:
--   EN_TOLERANCIA         aún no vence la tolerancia (o falta procesarla)
--   SALIDA_EN_TOLERANCIA  salió antes de 15 minutos: sin cobro
--   NO_APLICA             visitante, bicicleta o patineta: sin cobro
--   COBRADA               esta visita generó el cobro del día (id_cobro)
--   CUBIERTA              el cobro del día ya existía y cubre este vehículo (id_cobro): no se cobra otra vez
--   DIFERENCIA            vehículo de tarifa mayor que lo ya cobrado hoy: se cobró solo la diferencia (id_cobro)
--   LEGADO                visita del esquema anterior (un pago por visita)
-- visita_abierta vale id_estudiante solo mientras una persona registrada
-- tiene la visita EN_CURSO; el índice UNIQUE impide dos visitas abiertas de
-- la misma persona, incluso con solicitudes simultáneas (RN-07, RN-13).
-- Los visitantes quedan fuera de esa restricción.
-- ---------------------------------------------------------------------
CREATE TABLE visitas (
    id_visita        INT UNSIGNED                          NOT NULL AUTO_INCREMENT,
    id_estudiante    INT UNSIGNED                          NOT NULL,
    tipo_vehiculo    ENUM('CARRO', 'MOTO', 'BICICLETA', 'PATINETA') NOT NULL,
    tipo_origen      ENUM('IA', 'MANUAL')                  NOT NULL DEFAULT 'MANUAL',
    categoria        ENUM('ESTUDIANTE', 'PROFESOR', 'TRABAJADOR', 'VISITANTE') NOT NULL,
    id_tarifa        INT UNSIGNED                          NULL,
    tarifa_aplicada  DECIMAL(10, 2)                        NULL,
    entrada_en       DATETIME                              NOT NULL DEFAULT CURRENT_TIMESTAMP,
    entrada_por      INT UNSIGNED                          NOT NULL,
    tolerancia_hasta DATETIME                              NULL,
    salida_en        DATETIME                              NULL,
    salida_por       INT UNSIGNED                          NULL,
    estado           ENUM('EN_CURSO', 'CERRADA', 'ANULADA') NOT NULL DEFAULT 'EN_CURSO',
    liquidacion      ENUM('LEGADO', 'EN_TOLERANCIA', 'SALIDA_EN_TOLERANCIA', 'NO_APLICA', 'COBRADA', 'CUBIERTA', 'DIFERENCIA') NOT NULL DEFAULT 'EN_TOLERANCIA',
    id_cobro         INT UNSIGNED                          NULL,
    anulada_en       DATETIME                              NULL,
    anulada_por      INT UNSIGNED                          NULL,
    motivo_anulacion VARCHAR(255)                          NULL,
    visita_abierta   INT UNSIGNED AS (IF(estado = 'EN_CURSO' AND categoria <> 'VISITANTE', id_estudiante, NULL)) STORED,
    PRIMARY KEY (id_visita),
    CONSTRAINT uq_visitas_abierta UNIQUE (visita_abierta),
    KEY ix_visitas_estudiante (id_estudiante, entrada_en),
    KEY ix_visitas_estado (estado, entrada_en),
    KEY ix_visitas_entrada (entrada_en),
    KEY ix_visitas_tarifa (id_tarifa, tipo_vehiculo, categoria),
    KEY ix_visitas_entrada_por (entrada_por),
    KEY ix_visitas_salida_por (salida_por),
    KEY ix_visitas_anulada_por (anulada_por),
    KEY ix_visitas_liquidacion (liquidacion, tolerancia_hasta),
    KEY ix_visitas_cobro (id_cobro),
    CONSTRAINT fk_visitas_estudiante  FOREIGN KEY (id_estudiante) REFERENCES estudiantes (id_estudiante),
    -- La FK compuesta garantiza que el tipo y la categoría de la visita coincidan con los de la tarifa usada.
    CONSTRAINT fk_visitas_tarifa      FOREIGN KEY (id_tarifa, tipo_vehiculo, categoria) REFERENCES tarifas (id_tarifa, tipo_vehiculo, categoria),
    CONSTRAINT fk_visitas_entrada_por FOREIGN KEY (entrada_por) REFERENCES usuarios (id_usuario),
    CONSTRAINT fk_visitas_salida_por  FOREIGN KEY (salida_por)  REFERENCES usuarios (id_usuario),
    CONSTRAINT fk_visitas_anulada_por FOREIGN KEY (anulada_por) REFERENCES usuarios (id_usuario),
    CONSTRAINT ck_visitas_tarifa  CHECK (tarifa_aplicada >= 0),
    CONSTRAINT ck_visitas_legado  CHECK (liquidacion <> 'LEGADO' OR (id_tarifa IS NOT NULL AND tarifa_aplicada IS NOT NULL)),
    CONSTRAINT ck_visitas_cobro   CHECK (liquidacion NOT IN ('COBRADA', 'CUBIERTA', 'DIFERENCIA') OR id_cobro IS NOT NULL),
    CONSTRAINT ck_visitas_salida  CHECK (salida_en IS NULL OR salida_en >= entrada_en),
    CONSTRAINT ck_visitas_estado  CHECK (
        (estado = 'EN_CURSO' AND salida_en IS NULL AND salida_por IS NULL AND anulada_en IS NULL)
     OR (estado = 'CERRADA'  AND salida_en IS NOT NULL AND salida_por IS NOT NULL AND anulada_en IS NULL)
     OR (estado = 'ANULADA'  AND salida_en IS NULL AND anulada_en IS NOT NULL AND anulada_por IS NOT NULL
                             AND motivo_anulacion IS NOT NULL)
    )
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Consecutivos de comprobantes. Se incrementan dentro de la transacción
-- del pago, así que un pago revertido no deja huecos en la numeración.
-- ---------------------------------------------------------------------
CREATE TABLE consecutivos (
    nombre VARCHAR(20)  NOT NULL,
    ultimo INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (nombre)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO consecutivos (nombre, ultimo) VALUES ('RECIBO', 0), ('RECARGA', 0);

-- ---------------------------------------------------------------------
-- Cobro diario: como máximo UN cobro DIARIO vigente por persona y fecha, sin
-- importar cuántas veces entre y salga ese día (cobro_vigente es UNIQUE).
-- El total del día es la tarifa MÁS ALTA de los vehículos usados: si después
-- usa un vehículo de tarifa mayor, se agrega un cobro DIFERENCIA solo por la
-- diferencia (uno por visita: diferencia_vigente es UNIQUE).
-- Se genera cuando una visita con tarifa supera la tolerancia. Copia el
-- vehículo, la categoría y el valor de la tarifa que rigió en esa entrada.
-- PENDIENTE: sin saldo suficiente, se paga en Caja · PAGADO · ANULADO.
-- ---------------------------------------------------------------------
CREATE TABLE cobros_diarios (
    id_cobro         INT UNSIGNED                              NOT NULL AUTO_INCREMENT,
    id_estudiante    INT UNSIGNED                              NOT NULL,
    fecha            DATE                                      NOT NULL,
    concepto         ENUM('DIARIO', 'DIFERENCIA')              NOT NULL DEFAULT 'DIARIO',
    id_visita        INT UNSIGNED                              NOT NULL,
    tipo_vehiculo    ENUM('CARRO', 'MOTO', 'BICICLETA', 'PATINETA') NOT NULL,
    categoria        ENUM('ESTUDIANTE', 'PROFESOR', 'TRABAJADOR', 'VISITANTE') NOT NULL,
    id_tarifa        INT UNSIGNED                              NOT NULL,
    valor            DECIMAL(10, 2)                            NOT NULL,
    estado           ENUM('PENDIENTE', 'PAGADO', 'ANULADO')    NOT NULL DEFAULT 'PENDIENTE',
    generado_en      DATETIME                                  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    pagado_en        DATETIME                                  NULL,
    notificado_en    DATETIME                                  NULL,
    anulado_en       DATETIME                                  NULL,
    anulado_por      INT UNSIGNED                              NULL,
    motivo_anulacion VARCHAR(255)                              NULL,
    cobro_vigente    VARCHAR(24) AS (IF(estado <> 'ANULADO' AND concepto = 'DIARIO', CONCAT(id_estudiante, '@', fecha), NULL)) STORED,
    diferencia_vigente INT UNSIGNED AS (IF(estado <> 'ANULADO' AND concepto = 'DIFERENCIA', id_visita, NULL)) STORED,
    PRIMARY KEY (id_cobro),
    CONSTRAINT uq_cobros_vigente UNIQUE (cobro_vigente),
    CONSTRAINT uq_cobros_diferencia UNIQUE (diferencia_vigente),
    KEY ix_cobros_estado (estado, fecha),
    KEY ix_cobros_persona (id_estudiante, fecha),
    KEY ix_cobros_visita (id_visita),
    KEY ix_cobros_tarifa (id_tarifa, tipo_vehiculo, categoria),
    KEY ix_cobros_anulado_por (anulado_por),
    CONSTRAINT fk_cobros_estudiante FOREIGN KEY (id_estudiante) REFERENCES estudiantes (id_estudiante),
    CONSTRAINT fk_cobros_visita     FOREIGN KEY (id_visita)     REFERENCES visitas (id_visita),
    CONSTRAINT fk_cobros_tarifa     FOREIGN KEY (id_tarifa, tipo_vehiculo, categoria) REFERENCES tarifas (id_tarifa, tipo_vehiculo, categoria),
    CONSTRAINT fk_cobros_anulado    FOREIGN KEY (anulado_por)   REFERENCES usuarios (id_usuario),
    CONSTRAINT ck_cobros_valor  CHECK (valor >= 0),
    CONSTRAINT ck_cobros_estado CHECK (
        (estado = 'PENDIENTE' AND pagado_en IS NULL AND anulado_en IS NULL)
     OR (estado = 'PAGADO'    AND pagado_en IS NOT NULL AND anulado_en IS NULL)
     OR (estado = 'ANULADO'   AND anulado_en IS NOT NULL AND anulado_por IS NOT NULL AND motivo_anulacion IS NOT NULL)
    )
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

ALTER TABLE visitas
    ADD CONSTRAINT fk_visitas_cobro FOREIGN KEY (id_cobro) REFERENCES cobros_diarios (id_cobro);

-- ---------------------------------------------------------------------
-- Pagos: un único pago válido por cobro diario (pago_cobro_valido) o, en
-- visitas LEGADO, por visita (pago_valido) (RN-09, RF-11).
-- Un pago ANULADO conserva la fila y deja el cobro otra vez pendiente.
-- El débito automático del saldo usa el medio SALDO y no tiene cajero.
-- No se almacenan datos de tarjetas.
-- ---------------------------------------------------------------------
CREATE TABLE pagos (
    id_pago          INT UNSIGNED                                   NOT NULL AUTO_INCREMENT,
    id_visita        INT UNSIGNED                                   NULL,
    id_cobro         INT UNSIGNED                                   NULL,
    monto            DECIMAL(10, 2)                                 NOT NULL,
    pagado_en        DATETIME                                       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    registrado_por   INT UNSIGNED                                   NULL,
    medio_pago       ENUM('EFECTIVO', 'TRANSFERENCIA', 'DATAFONO', 'SALDO') NOT NULL,
    estado           ENUM('PAGADO', 'ANULADO')                      NOT NULL DEFAULT 'PAGADO',
    consecutivo      VARCHAR(12)                                    NOT NULL,
    referencia       VARCHAR(60)                                    NULL,
    anulado_en       DATETIME                                       NULL,
    anulado_por      INT UNSIGNED                                   NULL,
    motivo_anulacion VARCHAR(255)                                   NULL,
    pago_valido      INT UNSIGNED AS (IF(estado = 'PAGADO', id_visita, NULL)) STORED,
    pago_cobro_valido INT UNSIGNED AS (IF(estado = 'PAGADO', id_cobro, NULL)) STORED,
    PRIMARY KEY (id_pago),
    CONSTRAINT uq_pagos_consecutivo UNIQUE (consecutivo),
    CONSTRAINT uq_pagos_valido      UNIQUE (pago_valido),
    CONSTRAINT uq_pagos_cobro_valido UNIQUE (pago_cobro_valido),
    KEY ix_pagos_visita (id_visita),
    KEY ix_pagos_cobro (id_cobro),
    KEY ix_pagos_fecha (pagado_en),
    KEY ix_pagos_cajero (registrado_por, pagado_en),
    KEY ix_pagos_anulado_por (anulado_por),
    CONSTRAINT fk_pagos_visita      FOREIGN KEY (id_visita)      REFERENCES visitas (id_visita),
    CONSTRAINT fk_pagos_cobro       FOREIGN KEY (id_cobro)       REFERENCES cobros_diarios (id_cobro),
    CONSTRAINT fk_pagos_usuario     FOREIGN KEY (registrado_por) REFERENCES usuarios (id_usuario),
    CONSTRAINT fk_pagos_anulado_por FOREIGN KEY (anulado_por)    REFERENCES usuarios (id_usuario),
    CONSTRAINT ck_pagos_monto  CHECK (monto >= 0),
    CONSTRAINT ck_pagos_destino CHECK ((id_visita IS NULL) <> (id_cobro IS NULL)),
    CONSTRAINT ck_pagos_cajero  CHECK (registrado_por IS NOT NULL OR medio_pago = 'SALDO'),
    CONSTRAINT ck_pagos_estado CHECK (
        (estado = 'PAGADO'  AND anulado_en IS NULL AND anulado_por IS NULL)
     OR (estado = 'ANULADO' AND anulado_en IS NOT NULL AND anulado_por IS NOT NULL AND motivo_anulacion IS NOT NULL)
    )
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Auditoría: bitácora de operaciones sensibles (RN-10, RF-17).
-- ---------------------------------------------------------------------
CREATE TABLE auditoria (
    id_auditoria BIGINT UNSIGNED                     NOT NULL AUTO_INCREMENT,
    id_usuario   INT UNSIGNED                        NULL,
    accion       VARCHAR(40)                         NOT NULL,
    entidad      VARCHAR(40)                         NOT NULL,
    id_registro  BIGINT UNSIGNED                     NULL,
    resultado    ENUM('EXITO', 'DENEGADO', 'ERROR')  NOT NULL,
    motivo       VARCHAR(255)                        NULL,
    ip_origen    VARCHAR(45)                         NULL,
    creado_en    DATETIME                            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    detalle_json LONGTEXT                            NULL,
    PRIMARY KEY (id_auditoria),
    KEY ix_auditoria_fecha (creado_en),
    KEY ix_auditoria_accion (accion, creado_en),
    KEY ix_auditoria_usuario (id_usuario, creado_en),
    KEY ix_auditoria_registro (entidad, id_registro),
    CONSTRAINT fk_auditoria_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario),
    CONSTRAINT ck_auditoria_json CHECK (detalle_json IS NULL OR JSON_VALID(detalle_json))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------------
-- Movimientos de saldo: recargas en Caja, débitos automáticos y reintegros.
-- Es un libro inmutable; estudiantes.saldo guarda el saldo vigente.
-- ---------------------------------------------------------------------------------
CREATE TABLE movimientos_saldo (
    id_movimiento   BIGINT UNSIGNED                              NOT NULL AUTO_INCREMENT,
    id_estudiante   INT UNSIGNED                                 NOT NULL,
    tipo            ENUM('RECARGA', 'DEBITO', 'REINTEGRO')       NOT NULL,
    monto           DECIMAL(10, 2)                               NOT NULL,
    saldo_anterior  DECIMAL(10, 2)                               NOT NULL,
    saldo_nuevo     DECIMAL(10, 2)                               NOT NULL,
    id_cobro        INT UNSIGNED                                 NULL,
    id_pago         INT UNSIGNED                                 NULL,
    medio_pago      ENUM('EFECTIVO', 'TRANSFERENCIA', 'DATAFONO') NULL,
    referencia      VARCHAR(60)                                  NULL,
    consecutivo     VARCHAR(12)                                  NULL,
    registrado_por  INT UNSIGNED                                 NULL,
    creado_en       DATETIME                                     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- Un DEBITO por pago (no por cobro): si un pago con saldo se anula, el cobro puede volver a debitarse
    -- con un pago nuevo. El doble cobro lo impide uq_pagos_cobro_valido (un pago válido por cobro).
    debito_unico    INT UNSIGNED AS (IF(tipo = 'DEBITO', id_pago, NULL)) STORED,
    reintegro_unico INT UNSIGNED AS (IF(tipo = 'REINTEGRO', id_pago, NULL)) STORED,
    PRIMARY KEY (id_movimiento),
    CONSTRAINT uq_movimientos_debito    UNIQUE (debito_unico),
    CONSTRAINT uq_movimientos_reintegro UNIQUE (reintegro_unico),
    CONSTRAINT uq_movimientos_consec    UNIQUE (consecutivo),
    KEY ix_movimientos_persona (id_estudiante, creado_en),
    KEY ix_movimientos_cajero (registrado_por, creado_en),
    KEY ix_movimientos_cobro (id_cobro),
    KEY ix_movimientos_pago (id_pago),
    CONSTRAINT fk_movimientos_estudiante FOREIGN KEY (id_estudiante)  REFERENCES estudiantes (id_estudiante),
    CONSTRAINT fk_movimientos_cobro      FOREIGN KEY (id_cobro)       REFERENCES cobros_diarios (id_cobro),
    CONSTRAINT fk_movimientos_pago       FOREIGN KEY (id_pago)        REFERENCES pagos (id_pago),
    CONSTRAINT fk_movimientos_usuario    FOREIGN KEY (registrado_por) REFERENCES usuarios (id_usuario),
    CONSTRAINT ck_movimientos_monto  CHECK (monto > 0),
    CONSTRAINT ck_movimientos_saldo  CHECK (saldo_nuevo >= 0),
    CONSTRAINT ck_movimientos_cuadre CHECK (
        (tipo = 'DEBITO' AND saldo_nuevo = saldo_anterior - monto)
     OR (tipo IN ('RECARGA', 'REINTEGRO') AND saldo_nuevo = saldo_anterior + monto)
    ),
    CONSTRAINT ck_movimientos_debito    CHECK (tipo <> 'DEBITO' OR (id_cobro IS NOT NULL AND id_pago IS NOT NULL)),
    CONSTRAINT ck_movimientos_recarga   CHECK (tipo <> 'RECARGA' OR (medio_pago IS NOT NULL AND registrado_por IS NOT NULL AND consecutivo IS NOT NULL)),
    CONSTRAINT ck_movimientos_reintegro CHECK (tipo <> 'REINTEGRO' OR id_pago IS NOT NULL)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Avisos para la persona: se muestran en "Mi cuenta" y se envían como notificación push.
CREATE TABLE notificaciones (
    id_notificacion BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_estudiante   INT UNSIGNED    NOT NULL,
    id_cobro        INT UNSIGNED    NULL,
    tipo            VARCHAR(30)     NOT NULL,
    titulo          VARCHAR(120)    NOT NULL,
    mensaje         VARCHAR(255)    NOT NULL,
    creado_en       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    enviada_push    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    leida_en        DATETIME        NULL,
    PRIMARY KEY (id_notificacion),
    -- Un mismo aviso de un cobro no se repite.
    CONSTRAINT uq_notificaciones_cobro UNIQUE (id_cobro, tipo),
    KEY ix_notificaciones_persona (id_estudiante, creado_en),
    CONSTRAINT fk_notificaciones_estudiante FOREIGN KEY (id_estudiante) REFERENCES estudiantes (id_estudiante),
    CONSTRAINT fk_notificaciones_cobro      FOREIGN KEY (id_cobro)      REFERENCES cobros_diarios (id_cobro)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Navegadores (celulares) donde la persona aceptó recibir notificaciones.
-- Sin DELETE: una suscripción vencida se marca inactiva.
CREATE TABLE push_suscripciones (
    id_suscripcion INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    id_usuario     INT UNSIGNED  NOT NULL,
    endpoint       VARCHAR(600)  NOT NULL,
    endpoint_hash  CHAR(64)      NOT NULL,
    p256dh         VARCHAR(200)  NOT NULL,
    auth           VARCHAR(100)  NOT NULL,
    navegador      VARCHAR(160)  NULL,
    activa         TINYINT(1)    NOT NULL DEFAULT 1,
    creado_en      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultimo_envio   DATETIME      NULL,
    ultimo_error   VARCHAR(255)  NULL,
    PRIMARY KEY (id_suscripcion),
    CONSTRAINT uq_push_endpoint UNIQUE (endpoint_hash),
    KEY ix_push_usuario (id_usuario, activa),
    CONSTRAINT fk_push_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario),
    CONSTRAINT ck_push_activa  CHECK (activa IN (0, 1))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Última clase detectada por el celular de cámara de cada puesto de portería.
-- Solo se guarda la categoría del vehículo y la hora: nunca imágenes, placas ni colores.
CREATE TABLE puestos_camara (
    id_puesto       TINYINT UNSIGNED NOT NULL,
    nombre          VARCHAR(60)      NOT NULL,
    tipo_detectado  ENUM('CARRO', 'MOTO', 'BICICLETA', 'PATINETA', 'DESCONOCIDO') NULL,
    confianza       DECIMAL(5, 4)    NULL,
    detectado_en    DATETIME(3)      NULL,
    actualizado_por INT UNSIGNED     NULL,
    PRIMARY KEY (id_puesto),
    CONSTRAINT fk_puestos_usuario FOREIGN KEY (actualizado_por) REFERENCES usuarios (id_usuario),
    CONSTRAINT ck_puestos_confianza CHECK (confianza IS NULL OR (confianza >= 0 AND confianza <= 1))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Migraciones aplicadas (scripts/migrar.php). Una instalación nueva ya
-- incluye todas las de database/migrations: base.sql las registra.
-- ---------------------------------------------------------------------
CREATE TABLE migraciones (
    archivo     VARCHAR(120) NOT NULL,
    aplicada_en DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (archivo)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- Disparadores de protección del historial (RN-11, RN-12).
-- Refuerzan en la base de datos lo que la aplicación ya controla.
-- ---------------------------------------------------------------------
DELIMITER $$

CREATE TRIGGER tr_auditoria_no_update BEFORE UPDATE ON auditoria FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La auditoria no se puede modificar';
END$$

CREATE TRIGGER tr_auditoria_no_delete BEFORE DELETE ON auditoria FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La auditoria no se puede borrar';
END$$

CREATE TRIGGER tr_visitas_no_delete BEFORE DELETE ON visitas FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Las visitas no se borran: use anulacion auditada';
END$$

CREATE TRIGGER tr_pagos_no_delete BEFORE DELETE ON pagos FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los pagos no se borran: use anulacion auditada';
END$$

CREATE TRIGGER tr_visitas_inmutables BEFORE UPDATE ON visitas FOR EACH ROW
BEGIN
    IF OLD.estado <> 'EN_CURSO' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Una visita cerrada o anulada no se puede modificar';
    END IF;
    IF NOT (NEW.id_estudiante <=> OLD.id_estudiante)
       OR NOT (NEW.tipo_vehiculo <=> OLD.tipo_vehiculo)
       OR NOT (NEW.tipo_origen <=> OLD.tipo_origen)
       OR NOT (NEW.categoria <=> OLD.categoria)
       OR NOT (NEW.id_tarifa <=> OLD.id_tarifa)
       OR NOT (NEW.tarifa_aplicada <=> OLD.tarifa_aplicada)
       OR NOT (NEW.entrada_en <=> OLD.entrada_en)
       OR NOT (NEW.entrada_por <=> OLD.entrada_por)
       OR NOT (NEW.tolerancia_hasta <=> OLD.tolerancia_hasta) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los datos de entrada y la tarifa aplicada no se modifican';
    END IF;
    IF OLD.id_cobro IS NOT NULL AND NOT (NEW.id_cobro <=> OLD.id_cobro) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El cobro asociado a la visita no se cambia';
    END IF;
END$$

CREATE TRIGGER tr_pagos_inmutables BEFORE UPDATE ON pagos FOR EACH ROW
BEGIN
    IF OLD.estado <> 'PAGADO' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Un pago anulado no se puede modificar';
    END IF;
    IF NOT (NEW.id_visita <=> OLD.id_visita)
       OR NOT (NEW.id_cobro <=> OLD.id_cobro)
       OR NOT (NEW.monto <=> OLD.monto)
       OR NOT (NEW.pagado_en <=> OLD.pagado_en)
       OR NOT (NEW.registrado_por <=> OLD.registrado_por)
       OR NOT (NEW.medio_pago <=> OLD.medio_pago)
       OR NOT (NEW.consecutivo <=> OLD.consecutivo) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Un pago confirmado solo puede anularse';
    END IF;
END$$

CREATE TRIGGER tr_cobros_no_delete BEFORE DELETE ON cobros_diarios FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los cobros no se borran: use anulacion auditada';
END$$

CREATE TRIGGER tr_cobros_inmutables BEFORE UPDATE ON cobros_diarios FOR EACH ROW
BEGIN
    IF OLD.estado = 'ANULADO' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Un cobro anulado no se puede modificar';
    END IF;
    IF NOT (NEW.id_estudiante <=> OLD.id_estudiante)
       OR NOT (NEW.fecha <=> OLD.fecha)
       OR NOT (NEW.concepto <=> OLD.concepto)
       OR NOT (NEW.id_visita <=> OLD.id_visita)
       OR NOT (NEW.tipo_vehiculo <=> OLD.tipo_vehiculo)
       OR NOT (NEW.categoria <=> OLD.categoria)
       OR NOT (NEW.id_tarifa <=> OLD.id_tarifa)
       OR NOT (NEW.valor <=> OLD.valor)
       OR NOT (NEW.generado_en <=> OLD.generado_en) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El valor y los datos de un cobro no se modifican';
    END IF;
END$$

CREATE TRIGGER tr_movimientos_no_update BEFORE UPDATE ON movimientos_saldo FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los movimientos de saldo no se modifican';
END$$

CREATE TRIGGER tr_movimientos_no_delete BEFORE DELETE ON movimientos_saldo FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los movimientos de saldo no se borran';
END$$

CREATE TRIGGER tr_tarifas_historicas BEFORE UPDATE ON tarifas FOR EACH ROW
BEGIN
    IF NEW.valor <> OLD.valor
       OR NEW.tipo_vehiculo <> OLD.tipo_vehiculo
       OR NEW.categoria <> OLD.categoria
       OR NEW.vigente_desde <> OLD.vigente_desde THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Una tarifa no se edita: registre una nueva vigencia';
    END IF;
END$$

DELIMITER ;

-- =====================================================================
-- Eventos de acceso, datos institucionales, reverso de recargas e importaciones
-- (igual que database/migrations/20261108_0900_eventos_acceso_importacion.sql)
-- =====================================================================

-- Personas: datos institucionales -------------------------------------------------
ALTER TABLE estudiantes
    ADD COLUMN programa VARCHAR(120) NULL AFTER categoria,
    ADD COLUMN origen ENUM('UNIPARK', 'INSTITUCIONAL') NOT NULL DEFAULT 'UNIPARK' AFTER activo,
    ADD COLUMN sincronizado_en DATETIME NULL AFTER origen;

-- Celular de cámara: también la probabilidad de la segunda opción --------------------
ALTER TABLE puestos_camara
    ADD COLUMN segunda DECIMAL(5, 4) NULL AFTER confianza,
    ADD CONSTRAINT ck_puestos_segunda CHECK (segunda IS NULL OR (segunda >= 0 AND segunda <= 1));

-- Eventos de acceso ----------------------------------------------------------------
CREATE TABLE eventos_acceso (
    id_evento        BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    tipo             ENUM('ENTRADA', 'SALIDA') NOT NULL,
    id_puesto        TINYINT UNSIGNED NOT NULL DEFAULT 1,
    id_estudiante    INT UNSIGNED     NULL,
    -- Solo si la identidad no está registrada (para que el portero admita a un visitante).
    documento_leido  VARCHAR(20)      NULL,
    nombre_leido     VARCHAR(80)      NULL,
    id_visita        INT UNSIGNED     NULL,
    estado           ENUM('EN_CAPTURA', 'PENDIENTE_PORTERO', 'CONFIRMADO', 'RECHAZADO', 'EXPIRADO') NOT NULL DEFAULT 'EN_CAPTURA',
    motivo           ENUM('VEHICULO_NO_CLASIFICADO', 'VEHICULO_NO_COINCIDE', 'IDENTIDAD_NO_REGISTRADA',
                          'PERSONA_NO_AUTORIZADA', 'CAPTURA_REEMPLAZADA', 'SIN_ATENDER') NULL,
    tipo_detectado   ENUM('CARRO', 'MOTO', 'BICICLETA', 'PATINETA', 'DESCONOCIDO') NULL,
    confianza        DECIMAL(5, 4)    NULL,
    segunda          DECIMAL(5, 4)    NULL,
    fuente           ENUM('CELULAR', 'PUESTO') NULL,
    tipo_confirmado  ENUM('CARRO', 'MOTO', 'BICICLETA', 'PATINETA') NULL,
    metodo           ENUM('IA', 'MANUAL') NULL,
    resultado        VARCHAR(40)      NULL,
    mensaje          VARCHAR(255)     NULL,
    tiene_captura    TINYINT(1)       NOT NULL DEFAULT 0,
    creado_por       INT UNSIGNED     NOT NULL,
    resuelto_por     INT UNSIGNED     NULL,
    creado_en        DATETIME(3)      NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    clasificado_en   DATETIME(3)      NULL,
    resuelto_en      DATETIME(3)      NULL,
    -- Un punto de acceso captura un vehículo a la vez, una persona tiene un solo evento abierto y
    -- un documento no registrado genera un solo aviso aunque se escanee varias veces
    captura_puesto   TINYINT UNSIGNED AS (IF(estado = 'EN_CAPTURA', id_puesto, NULL)) STORED,
    abierto_persona  INT UNSIGNED     AS (IF(estado IN ('EN_CAPTURA', 'PENDIENTE_PORTERO'), id_estudiante, NULL)) STORED,
    abierto_documento VARCHAR(20)     AS (IF(estado IN ('EN_CAPTURA', 'PENDIENTE_PORTERO') AND id_estudiante IS NULL, documento_leido, NULL)) STORED,
    PRIMARY KEY (id_evento),
    CONSTRAINT uq_eventos_captura_puesto  UNIQUE (captura_puesto),
    CONSTRAINT uq_eventos_abierto_persona UNIQUE (abierto_persona),
    CONSTRAINT uq_eventos_abierto_doc     UNIQUE (abierto_documento),
    KEY ix_eventos_estado (estado, creado_en),
    KEY ix_eventos_persona (id_estudiante, creado_en),
    KEY ix_eventos_visita (id_visita),
    KEY ix_eventos_creado_por (creado_por),
    KEY ix_eventos_resuelto_por (resuelto_por),
    CONSTRAINT fk_eventos_puesto     FOREIGN KEY (id_puesto)     REFERENCES puestos_camara (id_puesto),
    CONSTRAINT fk_eventos_estudiante FOREIGN KEY (id_estudiante) REFERENCES estudiantes (id_estudiante),
    CONSTRAINT fk_eventos_visita     FOREIGN KEY (id_visita)     REFERENCES visitas (id_visita),
    CONSTRAINT fk_eventos_creado     FOREIGN KEY (creado_por)    REFERENCES usuarios (id_usuario),
    CONSTRAINT fk_eventos_resuelto   FOREIGN KEY (resuelto_por)  REFERENCES usuarios (id_usuario),
    CONSTRAINT ck_eventos_confianza  CHECK (confianza IS NULL OR (confianza >= 0 AND confianza <= 1)),
    CONSTRAINT ck_eventos_segunda    CHECK (segunda IS NULL OR (segunda >= 0 AND segunda <= 1)),
    CONSTRAINT ck_eventos_identidad  CHECK (id_estudiante IS NOT NULL OR documento_leido IS NOT NULL),
    CONSTRAINT ck_eventos_pendiente  CHECK (estado <> 'PENDIENTE_PORTERO' OR motivo IS NOT NULL),
    CONSTRAINT ck_eventos_confirmado CHECK (estado <> 'CONFIRMADO' OR (tipo_confirmado IS NOT NULL AND metodo IS NOT NULL)),
    CONSTRAINT ck_eventos_manual     CHECK (metodo <> 'MANUAL' OR resuelto_por IS NOT NULL)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Visitas: eventos de entrada y salida que las respaldan --------------------------------
ALTER TABLE visitas
    ADD COLUMN id_evento_entrada BIGINT UNSIGNED NULL AFTER id_cobro,
    ADD COLUMN id_evento_salida  BIGINT UNSIGNED NULL AFTER id_evento_entrada,
    ADD KEY ix_visitas_evento_entrada (id_evento_entrada),
    ADD KEY ix_visitas_evento_salida (id_evento_salida),
    ADD CONSTRAINT fk_visitas_evento_entrada FOREIGN KEY (id_evento_entrada) REFERENCES eventos_acceso (id_evento),
    ADD CONSTRAINT fk_visitas_evento_salida  FOREIGN KEY (id_evento_salida)  REFERENCES eventos_acceso (id_evento);

-- Movimientos de saldo: reverso controlado de una recarga -------------------------------
ALTER TABLE movimientos_saldo
    MODIFY tipo ENUM('RECARGA', 'DEBITO', 'REINTEGRO', 'REVERSO_RECARGA') NOT NULL,
    ADD COLUMN id_movimiento_origen BIGINT UNSIGNED NULL AFTER id_pago,
    ADD COLUMN motivo VARCHAR(255) NULL AFTER referencia,
    DROP CONSTRAINT ck_movimientos_cuadre;

ALTER TABLE movimientos_saldo
    ADD COLUMN reverso_unico BIGINT UNSIGNED AS (IF(tipo = 'REVERSO_RECARGA', id_movimiento_origen, NULL)) STORED,
    ADD CONSTRAINT uq_movimientos_reverso UNIQUE (reverso_unico),
    ADD KEY ix_movimientos_origen (id_movimiento_origen),
    ADD CONSTRAINT fk_movimientos_origen FOREIGN KEY (id_movimiento_origen) REFERENCES movimientos_saldo (id_movimiento),
    ADD CONSTRAINT ck_movimientos_cuadre CHECK (
        (tipo IN ('DEBITO', 'REVERSO_RECARGA') AND saldo_nuevo = saldo_anterior - monto)
     OR (tipo IN ('RECARGA', 'REINTEGRO') AND saldo_nuevo = saldo_anterior + monto)
    ),
    ADD CONSTRAINT ck_movimientos_reverso CHECK (
        tipo <> 'REVERSO_RECARGA' OR (id_movimiento_origen IS NOT NULL AND registrado_por IS NOT NULL AND motivo IS NOT NULL)
    );

-- Bitácora de importaciones de la base institucional (solo se inserta) -----------------
CREATE TABLE importaciones (
    id_importacion INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    archivo        VARCHAR(160)  NOT NULL,
    sha256         CHAR(64)      NOT NULL,
    modo           ENUM('VALIDAR', 'APLICAR') NOT NULL,
    filas          INT UNSIGNED  NOT NULL DEFAULT 0,
    creadas        INT UNSIGNED  NOT NULL DEFAULT 0,
    actualizadas   INT UNSIGNED  NOT NULL DEFAULT 0,
    sin_cambios    INT UNSIGNED  NOT NULL DEFAULT 0,
    rechazadas     INT UNSIGNED  NOT NULL DEFAULT 0,
    errores_json   LONGTEXT      NULL,
    realizado_por  INT UNSIGNED  NULL,
    creado_en      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_importacion),
    KEY ix_importaciones_fecha (creado_en),
    CONSTRAINT fk_importaciones_usuario FOREIGN KEY (realizado_por) REFERENCES usuarios (id_usuario),
    CONSTRAINT ck_importaciones_json CHECK (errores_json IS NULL OR JSON_VALID(errores_json))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Disparadores: los eventos cerrados no cambian y nada se borra -----------------------
DROP TRIGGER IF EXISTS tr_eventos_cerrados;
DROP TRIGGER IF EXISTS tr_eventos_no_delete;
DROP TRIGGER IF EXISTS tr_importaciones_no_delete;

DELIMITER $$

CREATE TRIGGER tr_eventos_cerrados BEFORE UPDATE ON eventos_acceso FOR EACH ROW
BEGIN
    IF OLD.estado IN ('CONFIRMADO', 'RECHAZADO', 'EXPIRADO') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Un evento de acceso cerrado no se puede modificar';
    END IF;
    IF NOT (NEW.tipo <=> OLD.tipo) OR NOT (NEW.id_estudiante <=> OLD.id_estudiante)
       OR NOT (NEW.creado_por <=> OLD.creado_por) OR NOT (NEW.creado_en <=> OLD.creado_en) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La identidad y el origen de un evento no se modifican';
    END IF;
END$$

CREATE TRIGGER tr_eventos_no_delete BEFORE DELETE ON eventos_acceso FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los eventos de acceso no se borran';
END$$

CREATE TRIGGER tr_importaciones_no_delete BEFORE DELETE ON importaciones FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La bitácora de importaciones no se borra';
END$$

DELIMITER ;

-- Recargas en línea del saldo prepago (pasarela de pagos) -------------------------------
-- Igual a database/migrations/20261108_1010_recargas_en_linea.sql
-- Una RECARGA en Caja exige el cajero que recibió el dinero. Una RECARGA en línea no tiene cajero: exige en
-- cambio la referencia de la transacción de la pasarela (quién la inició queda en recargas_en_linea.id_usuario).
ALTER TABLE movimientos_saldo
    MODIFY medio_pago ENUM('EFECTIVO', 'TRANSFERENCIA', 'DATAFONO', 'PASARELA') NULL,
    DROP CONSTRAINT ck_movimientos_recarga;

ALTER TABLE movimientos_saldo
    ADD CONSTRAINT ck_movimientos_recarga CHECK (tipo <> 'RECARGA' OR (medio_pago IS NOT NULL AND consecutivo IS NOT NULL AND (
        (medio_pago <> 'PASARELA' AND registrado_por IS NOT NULL)
     OR (medio_pago = 'PASARELA' AND referencia IS NOT NULL))));

CREATE TABLE recargas_en_linea (
    id_recarga               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    referencia               VARCHAR(40)     NOT NULL,
    id_estudiante            INT UNSIGNED    NOT NULL,
    id_usuario               INT UNSIGNED    NOT NULL,
    proveedor                VARCHAR(20)     NOT NULL,
    entorno                  ENUM('SANDBOX', 'PRODUCCION') NOT NULL,
    monto                    DECIMAL(10, 2)  NOT NULL,
    moneda                   CHAR(3)         NOT NULL DEFAULT 'COP',
    estado                   ENUM('CREADA', 'PENDIENTE', 'APROBADA', 'RECHAZADA', 'ANULADA', 'ERROR', 'CANCELADA', 'EXPIRADA') NOT NULL DEFAULT 'CREADA',
    -- Transacción de la pasarela que fijó el estado actual (verificada por el servidor).
    id_transaccion           VARCHAR(64)     NULL,
    -- Identificador que trajo el navegador al volver del checkout. NO confirma nada: solo permite
    -- que la conciliación del servidor consulte esa transacción a la pasarela.
    id_transaccion_reportada VARCHAR(64)     NULL,
    medio_externo            VARCHAR(30)     NULL,
    detalle_estado           VARCHAR(255)    NULL,
    id_movimiento            BIGINT UNSIGNED NULL,
    creada_en                DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expira_en                DATETIME        NOT NULL,
    actualizada_en           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    aprobada_en              DATETIME        NULL,
    consultada_en            DATETIME        NULL,
    PRIMARY KEY (id_recarga),
    CONSTRAINT uq_recargas_referencia UNIQUE (referencia),
    CONSTRAINT uq_recargas_movimiento UNIQUE (id_movimiento),
    KEY ix_recargas_persona (id_estudiante, creada_en),
    KEY ix_recargas_estado (estado, creada_en),
    KEY ix_recargas_transaccion (id_transaccion),
    CONSTRAINT fk_recargas_estudiante FOREIGN KEY (id_estudiante) REFERENCES estudiantes (id_estudiante),
    CONSTRAINT fk_recargas_usuario    FOREIGN KEY (id_usuario)    REFERENCES usuarios (id_usuario),
    CONSTRAINT fk_recargas_movimiento FOREIGN KEY (id_movimiento) REFERENCES movimientos_saldo (id_movimiento),
    CONSTRAINT ck_recargas_monto      CHECK (monto > 0),
    CONSTRAINT ck_recargas_moneda     CHECK (moneda = 'COP'),
    CONSTRAINT ck_recargas_aprobada   CHECK (estado <> 'APROBADA' OR (id_movimiento IS NOT NULL AND aprobada_en IS NOT NULL))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE notificaciones_pasarela (
    id_notificacion  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    proveedor        VARCHAR(20)     NOT NULL,
    origen           ENUM('WEBHOOK', 'CONSULTA') NOT NULL,
    -- SHA-256 del cuerpo recibido: un reenvío idéntico ya procesado se reconoce sin repetir nada.
    huella           CHAR(64)        NULL,
    tipo_evento      VARCHAR(60)     NULL,
    id_transaccion   VARCHAR(64)     NULL,
    referencia       VARCHAR(80)     NULL,
    estado_reportado VARCHAR(20)     NULL,
    monto_centavos   BIGINT          NULL,
    firma_valida     TINYINT(1)      NOT NULL DEFAULT 0,
    resultado        VARCHAR(30)     NOT NULL,
    mensaje          VARCHAR(255)    NULL,
    id_recarga       INT UNSIGNED    NULL,
    recibida_en      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_notificacion),
    CONSTRAINT uq_notif_pasarela_huella UNIQUE (huella),
    KEY ix_notif_pasarela_recarga (id_recarga),
    KEY ix_notif_pasarela_resultado (resultado, recibida_en),
    CONSTRAINT fk_notif_pasarela_recarga FOREIGN KEY (id_recarga) REFERENCES recargas_en_linea (id_recarga)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

DROP TRIGGER IF EXISTS tr_recargas_no_delete;
DROP TRIGGER IF EXISTS tr_recargas_inmutables;
DROP TRIGGER IF EXISTS tr_notif_pasarela_no_update;
DROP TRIGGER IF EXISTS tr_notif_pasarela_no_delete;

DELIMITER $$

CREATE TRIGGER tr_recargas_no_delete BEFORE DELETE ON recargas_en_linea FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Las recargas en linea no se borran';
END$$

CREATE TRIGGER tr_recargas_inmutables BEFORE UPDATE ON recargas_en_linea FOR EACH ROW
BEGIN
    IF OLD.estado = 'APROBADA' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Una recarga aprobada no se modifica: use un movimiento compensatorio';
    END IF;
    IF NOT (NEW.referencia <=> OLD.referencia) OR NOT (NEW.id_estudiante <=> OLD.id_estudiante)
       OR NOT (NEW.monto <=> OLD.monto) OR NOT (NEW.moneda <=> OLD.moneda)
       OR NOT (NEW.proveedor <=> OLD.proveedor) OR NOT (NEW.entorno <=> OLD.entorno)
       OR NOT (NEW.creada_en <=> OLD.creada_en) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los datos de origen de una recarga no se modifican';
    END IF;
END$$

CREATE TRIGGER tr_notif_pasarela_no_update BEFORE UPDATE ON notificaciones_pasarela FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La bitacora de la pasarela no se modifica';
END$$

CREATE TRIGGER tr_notif_pasarela_no_delete BEFORE DELETE ON notificaciones_pasarela FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La bitacora de la pasarela no se borra';
END$$

DELIMITER ;
