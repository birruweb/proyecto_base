-- ==========================================================
-- Actualización 001: módulos con grupos y submódulos
--
-- Solo para bases instaladas ANTES de esta versión.
-- Si instalas desde cero, usa instalar.sql (ya lo incluye).
-- Ejecútalo UNA sola vez (Alt+X en DBeaver).
-- ==========================================================

SET NAMES utf8mb4;
-- Selecciona tu base antes de ejecutarlo (en DBeaver, la conexión; en la terminal: mysql -u root TU_BASE < archivo.sql)

-- 1. Estructura: padre_id y ruta opcional (los grupos no tienen ruta)
ALTER TABLE modulos
    ADD COLUMN padre_id INT UNSIGNED NULL COMMENT 'Grupo al que pertenece (NULL = raíz)' AFTER id,
    MODIFY ruta VARCHAR(100) NULL COMMENT 'NULL en los grupos',
    ADD CONSTRAINT fk_modulos_padre FOREIGN KEY (padre_id) REFERENCES modulos(id);

-- 2. Grupos raíz
INSERT INTO modulos (clave, nombre, icono, ruta, orden) VALUES
    ('catalogos',      'Catálogos',      'bi-folder2-open', NULL, 10),
    ('administracion', 'Administración', 'bi-gear',         NULL, 90);

SET @catalogos = (SELECT id FROM modulos WHERE clave = 'catalogos');
SET @admin     = (SELECT id FROM modulos WHERE clave = 'administracion');

-- 3. Pantalla para administrar módulos
INSERT INTO modulos (padre_id, clave, nombre, icono, ruta, orden) VALUES
    (@admin, 'modulos', 'Módulos', 'bi-diagram-3', 'modulos', 30);

-- 4. Acomodar los módulos existentes dentro de los grupos
UPDATE modulos SET padre_id = @admin WHERE clave IN ('usuarios', 'roles');
UPDATE modulos SET padre_id = @catalogos, orden = 10 WHERE clave = 'productos';
