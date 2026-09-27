-- ==========================================================
-- Actualización 002: quién creó y quién modificó cada registro
--
-- Solo para bases instaladas ANTES de esta versión.
-- Ejecútalo UNA sola vez con SQL Editor -> Execute SQL Script.
-- ==========================================================

SET NAMES utf8mb4;
-- Selecciona tu base antes de ejecutarlo (en DBeaver, la conexión; en la terminal: mysql -u root TU_BASE < archivo.sql)

ALTER TABLE usuarios
    ADD COLUMN creado_por      INT UNSIGNED NULL AFTER creado_en,
    ADD COLUMN actualizado_por INT UNSIGNED NULL AFTER actualizado_en,
    ADD CONSTRAINT fk_usuarios_creado_por      FOREIGN KEY (creado_por)      REFERENCES usuarios(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_usuarios_actualizado_por FOREIGN KEY (actualizado_por) REFERENCES usuarios(id) ON DELETE SET NULL;

ALTER TABLE roles
    ADD COLUMN creado_por      INT UNSIGNED NULL AFTER creado_en,
    ADD COLUMN actualizado_por INT UNSIGNED NULL AFTER actualizado_en,
    ADD CONSTRAINT fk_roles_creado_por      FOREIGN KEY (creado_por)      REFERENCES usuarios(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_roles_actualizado_por FOREIGN KEY (actualizado_por) REFERENCES usuarios(id) ON DELETE SET NULL;

ALTER TABLE productos
    ADD COLUMN creado_por      INT UNSIGNED NULL AFTER creado_en,
    ADD COLUMN actualizado_por INT UNSIGNED NULL AFTER actualizado_en,
    ADD CONSTRAINT fk_productos_creado_por      FOREIGN KEY (creado_por)      REFERENCES usuarios(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_productos_actualizado_por FOREIGN KEY (actualizado_por) REFERENCES usuarios(id) ON DELETE SET NULL;

-- modulos no tenía fechas: se agregan las cuatro columnas
ALTER TABLE modulos
    ADD COLUMN creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN creado_por      INT UNSIGNED NULL,
    ADD COLUMN actualizado_en  TIMESTAMP    NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    ADD COLUMN actualizado_por INT UNSIGNED NULL,
    ADD CONSTRAINT fk_modulos_creado_por      FOREIGN KEY (creado_por)      REFERENCES usuarios(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_modulos_actualizado_por FOREIGN KEY (actualizado_por) REFERENCES usuarios(id) ON DELETE SET NULL;

-- ----------------------------------------------------------
-- TUS TABLAS (marcas, clientes, etc.): copia este bloque por
-- cada una, cambia "marcas" por el nombre de la tabla y quita
-- los guiones del inicio de cada línea.
-- ----------------------------------------------------------
-- ALTER TABLE marcas
--     ADD COLUMN creado_por      INT UNSIGNED NULL AFTER creado_en,
--     ADD COLUMN actualizado_por INT UNSIGNED NULL AFTER actualizado_en,
--     ADD CONSTRAINT fk_marcas_creado_por      FOREIGN KEY (creado_por)      REFERENCES usuarios(id) ON DELETE SET NULL,
--     ADD CONSTRAINT fk_marcas_actualizado_por FOREIGN KEY (actualizado_por) REFERENCES usuarios(id) ON DELETE SET NULL;
