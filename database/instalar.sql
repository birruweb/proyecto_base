-- ==========================================================
-- Proyecto Base: estructura y datos iniciales
--
-- Se puede ejecutar varias veces sin borrar datos
-- (CREATE ... IF NOT EXISTS / INSERT IGNORE).
--
-- Se instala en la base que tengas seleccionada. Lo más fácil:
--   C:\xampp\php\php consola\instalar.php
-- que crea la base con el DB_NAME del .env y ejecuta este archivo.
--
-- Usuario inicial:  admin  /  Admin123!   (rol Superadmin; cámbiala al entrar)
--
-- Roles:
--   Superadmin     acceso total, ignora permisos
--   Administrador  ver, crear y editar en todo; no puede eliminar
--   Consulta       ejemplo con permisos mínimos
-- ==========================================================

-- Acentos y ñ correctos aunque se importe desde la terminal
SET NAMES utf8mb4;

-- Algunas tablas apuntan a usuarios antes de que exista (auditoría):
-- se revisan las llaves foráneas al final
SET FOREIGN_KEY_CHECKS = 0;

-- ---------- Roles ----------
CREATE TABLE IF NOT EXISTS roles (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre         VARCHAR(50)  NOT NULL,
    descripcion    VARCHAR(255) NULL,
    es_superadmin  TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 = acceso total, ignora permisos',
    creado_en      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    creado_por     INT UNSIGNED NULL,
    actualizado_en TIMESTAMP    NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    actualizado_por INT UNSIGNED NULL,
    UNIQUE KEY uq_roles_nombre (nombre),
    CONSTRAINT fk_roles_creado_por      FOREIGN KEY (creado_por)      REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_roles_actualizado_por FOREIGN KEY (actualizado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Módulos (opciones del menú) ----------
--   Grupo raíz:    padre_id NULL y ruta NULL  -> encabezado desplegable del menú
--   Módulo raíz:   padre_id NULL y con ruta   -> enlace directo en el menú
--   Submódulo:     padre_id = un grupo        -> enlace dentro del grupo
CREATE TABLE IF NOT EXISTS modulos (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    padre_id  INT UNSIGNED NULL COMMENT 'Grupo al que pertenece (NULL = raíz)',
    clave     VARCHAR(50)  NOT NULL COMMENT 'Se usa en las rutas: clave.ver, clave.crear...',
    nombre    VARCHAR(100) NOT NULL,
    icono     VARCHAR(50)  NOT NULL DEFAULT 'bi-circle' COMMENT 'Clase de Bootstrap Icons',
    ruta      VARCHAR(100) NULL COMMENT 'NULL en los grupos',
    orden     INT          NOT NULL DEFAULT 0,
    activo    TINYINT(1)   NOT NULL DEFAULT 1,
    creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    creado_por      INT UNSIGNED NULL,
    actualizado_en  TIMESTAMP    NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    actualizado_por INT UNSIGNED NULL,
    UNIQUE KEY uq_modulos_clave (clave),
    CONSTRAINT fk_modulos_padre FOREIGN KEY (padre_id) REFERENCES modulos(id),
    CONSTRAINT fk_modulos_creado_por      FOREIGN KEY (creado_por)      REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_modulos_actualizado_por FOREIGN KEY (actualizado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Permisos por rol y módulo ----------
CREATE TABLE IF NOT EXISTS permisos (
    rol_id     INT UNSIGNED NOT NULL,
    modulo_id  INT UNSIGNED NOT NULL,
    ver        TINYINT(1) NOT NULL DEFAULT 0,
    crear      TINYINT(1) NOT NULL DEFAULT 0,
    editar     TINYINT(1) NOT NULL DEFAULT 0,
    eliminar   TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (rol_id, modulo_id),
    CONSTRAINT fk_permisos_rol    FOREIGN KEY (rol_id)    REFERENCES roles(id)   ON DELETE CASCADE,
    CONSTRAINT fk_permisos_modulo FOREIGN KEY (modulo_id) REFERENCES modulos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Usuarios ----------
CREATE TABLE IF NOT EXISTS usuarios (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre             VARCHAR(100) NOT NULL,
    usuario            VARCHAR(50)  NOT NULL,
    email              VARCHAR(150) NULL,
    password           VARCHAR(255) NOT NULL COMMENT 'Hash de password_hash(), nunca texto plano',
    rol_id             INT UNSIGNED NOT NULL,
    activo             TINYINT(1)   NOT NULL DEFAULT 1,
    intentos_fallidos  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    bloqueado_hasta    DATETIME     NULL,
    ultimo_acceso      DATETIME     NULL,
    creado_en          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    creado_por         INT UNSIGNED NULL,
    actualizado_en     TIMESTAMP    NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    actualizado_por    INT UNSIGNED NULL,
    UNIQUE KEY uq_usuarios_usuario (usuario),
    UNIQUE KEY uq_usuarios_email (email),
    CONSTRAINT fk_usuarios_rol FOREIGN KEY (rol_id) REFERENCES roles(id),
    CONSTRAINT fk_usuarios_creado_por      FOREIGN KEY (creado_por)      REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_usuarios_actualizado_por FOREIGN KEY (actualizado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Productos (módulo de ejemplo) ----------
CREATE TABLE IF NOT EXISTS productos (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre         VARCHAR(150)  NOT NULL,
    descripcion    TEXT          NULL,
    precio         DECIMAL(10,2) NOT NULL DEFAULT 0,
    stock          INT           NOT NULL DEFAULT 0,
    activo         TINYINT(1)    NOT NULL DEFAULT 1,
    creado_en      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    creado_por     INT UNSIGNED  NULL,
    actualizado_en TIMESTAMP     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    actualizado_por INT UNSIGNED NULL,
    CONSTRAINT fk_productos_creado_por      FOREIGN KEY (creado_por)      REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_productos_actualizado_por FOREIGN KEY (actualizado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================
-- Datos iniciales
-- ==========================================================

INSERT IGNORE INTO roles (id, nombre, descripcion, es_superadmin) VALUES
    (1, 'Superadmin', 'Acceso total al sistema', 1),
    (2, 'Consulta', 'Solo puede ver productos', 0),
    (3, 'Administrador', 'Todos los permisos menos eliminar', 0);

-- Primero los grupos (los submódulos apuntan a ellos)
INSERT IGNORE INTO modulos (id, padre_id, clave, nombre, icono, ruta, orden) VALUES
    (5, NULL, 'administracion', 'Administración',   'bi-gear',         NULL,        90),
    (6, NULL, 'catalogos',      'Catálogos',        'bi-folder2-open', NULL,        10),
    (1, 5,    'usuarios',       'Usuarios',         'bi-people',       'usuarios',  10),
    (2, 5,    'roles',          'Roles y permisos', 'bi-shield-lock',  'roles',     20),
    (4, 5,    'modulos',        'Módulos',          'bi-diagram-3',    'modulos',   30),
    (3, 6,    'productos',      'Productos',        'bi-box-seam',     'productos', 10);

-- Administrador: ver, crear y editar en usuarios, roles y módulos
INSERT IGNORE INTO permisos (rol_id, modulo_id, ver, crear, editar, eliminar) VALUES
    (3, 1, 1, 1, 1, 0),
    (3, 2, 1, 1, 1, 0),
    (3, 4, 1, 1, 1, 0);

-- Permisos de Productos (módulo de ejemplo)
INSERT IGNORE INTO permisos (rol_id, modulo_id, ver, crear, editar, eliminar) VALUES
    (2, 3, 1, 0, 0, 0),
    (3, 3, 1, 1, 1, 0);

-- Contraseña: Admin123!
INSERT IGNORE INTO usuarios (id, nombre, usuario, email, password, rol_id) VALUES
    (1, 'Administrador', 'admin', NULL, '$2y$12$sb3I2/9KT940zK5DY8eXWO/SbBjdc0F90EIji7XUuHCL8qIi20Q3m', 1);

INSERT IGNORE INTO productos (id, nombre, descripcion, precio, stock) VALUES
    (1, 'Teclado mecánico', 'Switches rojos, distribución en español', 1299.00, 15),
    (2, 'Mouse inalámbrico', 'Receptor USB, 1600 DPI', 349.50, 40),
    (3, 'Monitor 24"', 'Full HD, 75 Hz, HDMI y VGA', 2899.00, 6);

SET FOREIGN_KEY_CHECKS = 1;
