-- ================================================================
--  MIGRACIÓN: legislaturas + diputados (separados de usuarios)
--  Ejecutar una sola vez en phpMyAdmin o CLI de MySQL
-- ================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Tabla legislaturas
CREATE TABLE IF NOT EXISTS legislaturas (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    numero       INT          NOT NULL              COMMENT 'Ej: 64',
    clave        VARCHAR(20)  NOT NULL              COMMENT 'Ej: LXIV',
    nombre       VARCHAR(150) NOT NULL              COMMENT 'Ej: Sexagésima Cuarta',
    fecha_inicio DATE         NULL,
    fecha_fin    DATE         NULL,
    activa       TINYINT(1)   NOT NULL DEFAULT 0,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_clave (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Insertar legislatura actual (LXIV)
INSERT IGNORE INTO legislaturas (numero, clave, nombre, activa)
VALUES (64, 'LXIV', 'Sexagésima Cuarta', 1);

-- 3. Tabla diputados
CREATE TABLE IF NOT EXISTS diputados (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    legislatura_id INT              NOT NULL,
    nombre         VARCHAR(200)     NOT NULL,
    tipo_eleccion  ENUM('nominal','plurinominal') NOT NULL DEFAULT 'nominal',
    distrito       TINYINT UNSIGNED NULL          COMMENT '1-21, solo para nominales',
    tipo_mandato   ENUM('titular','suplente')     NOT NULL DEFAULT 'titular',
    suplente_de    INT              NULL          COMMENT 'FK al diputado titular que reemplaza',
    fecha_inicio   DATE             NULL,
    fecha_fin      DATE             NULL,
    activo         TINYINT(1)       NOT NULL DEFAULT 1,
    created_at     TIMESTAMP        DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_dip_legislatura FOREIGN KEY (legislatura_id) REFERENCES legislaturas(id),
    CONSTRAINT fk_dip_suplente    FOREIGN KEY (suplente_de)    REFERENCES diputados(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Migrar diputados existentes desde usuarios (iTipo=3)
--    Se preservan los IDs originales para no romper sesion_metadatos ni acta_metadata
INSERT IGNORE INTO diputados (id, legislatura_id, nombre, tipo_eleccion, tipo_mandato,
                               fecha_inicio, fecha_fin, activo)
SELECT
    u.iIdUsuario,
    1                 AS legislatura_id,
    u.cNombre,
    'nominal'         AS tipo_eleccion,
    'titular'         AS tipo_mandato,
    CASE WHEN COLUMN_EXISTS('usuarios','dDesde') THEN u.dDesde ELSE NULL END,
    CASE WHEN COLUMN_EXISTS('usuarios','dHasta') THEN u.dHasta ELSE NULL END,
    u.iStatus
FROM usuarios u
WHERE u.iTipo = 3;

-- 4b. Versión compatible si la anterior falla (sin COLUMN_EXISTS)
INSERT IGNORE INTO diputados (id, legislatura_id, nombre, tipo_eleccion, tipo_mandato, activo)
SELECT iIdUsuario, 1, cNombre, 'nominal', 'titular', iStatus
FROM usuarios
WHERE iTipo = 3
  AND iIdUsuario NOT IN (SELECT id FROM diputados);

-- 5. Ajustar AUTO_INCREMENT para evitar colisión con IDs migrados
SET @next_id = (SELECT IFNULL(MAX(id), 0) + 100 FROM diputados);
SET @sql_ai  = CONCAT('ALTER TABLE diputados AUTO_INCREMENT = ', @next_id);
PREPARE stmt_ai FROM @sql_ai;
EXECUTE stmt_ai;
DEALLOCATE PREPARE stmt_ai;

SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------------
-- NOTA: Los diputados en usuarios (iTipo=3) pueden quedar como
-- referencia histórica. Para limpiarlos ejecutar:
--   UPDATE usuarios SET iStatus=0 WHERE iTipo=3;
-- ----------------------------------------------------------------
