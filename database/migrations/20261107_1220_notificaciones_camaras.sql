-- Migración: 20261107_1220_notificaciones_camaras
-- Autor: UniPark
-- Motivo: avisos al celular cuando vence la tolerancia sin saldo suficiente (notificaciones y
--         suscripciones Web Push) y puestos de cámara para el modo de dos celulares
--         (un celular lee carnets y otro reconoce el vehículo).
-- Reversión: DROP TABLE puestos_camara, push_suscripciones, notificaciones;

SET NAMES utf8mb4;

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
