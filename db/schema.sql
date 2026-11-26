-- Esquema de FamilyCoins para MySQL/MariaDB (probado con MariaDB 10.4 de XAMPP).
-- Crear primero una base de datos vacía con charset utf8mb4 y ejecutar este
-- script sobre ella. Los datos de ejemplo se insertan al final.

CREATE TABLE usuarios (
    id             INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nombre         VARCHAR(100) NOT NULL,
    email          VARCHAR(150) NOT NULL UNIQUE,
    password_hash  VARCHAR(255) NOT NULL,
    codigo         VARCHAR(20)  NOT NULL UNIQUE,
    rol            VARCHAR(30)  NOT NULL,
    saldo_inicial  INTEGER,                        -- puede ser NULL
    fecha_nacimiento DATE,                         -- cumpleaños del usuario
    activo         BOOLEAN NOT NULL DEFAULT TRUE,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE parejas (
    id           INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    usuario_1_id INTEGER NOT NULL UNIQUE REFERENCES usuarios(id),
    usuario_2_id INTEGER NOT NULL UNIQUE REFERENCES usuarios(id),
    fecha_aniversario DATE,                        -- aniversario de la pareja
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (usuario_1_id <> usuario_2_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pareja_streaks (
    pareja_id           INTEGER PRIMARY KEY REFERENCES parejas(id) ON DELETE CASCADE,
    racha_actual        INTEGER NOT NULL DEFAULT 0,
    racha_maxima        INTEGER NOT NULL DEFAULT 0,
    ultimo_dia_completo DATE,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pareja_streak_checkins (
    pareja_id  INTEGER NOT NULL REFERENCES parejas(id) ON DELETE CASCADE,
    usuario_id INTEGER NOT NULL REFERENCES usuarios(id),
    fecha      DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (pareja_id, usuario_id, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_streak_checkins_fecha
    ON pareja_streak_checkins (pareja_id, fecha);

CREATE TABLE pareja_plantas (
    pareja_id       INTEGER PRIMARY KEY REFERENCES parejas(id) ON DELETE CASCADE,
    planta_id       VARCHAR(40) NOT NULL,
    etapa           INTEGER NOT NULL DEFAULT 0 CHECK (etapa BETWEEN 0 AND 4),
    etapa_maxima    INTEGER NOT NULL DEFAULT 0 CHECK (etapa_maxima BETWEEN 0 AND 4),
    progreso_dias   INTEGER NOT NULL DEFAULT 0 CHECK (progreso_dias >= 0),
    degradada_para  DATE,                          -- último día completo ya penalizado
    reiniciada_para DATE,                          -- último día completo que provocó el reset
    elegida_por_id  INTEGER NOT NULL REFERENCES usuarios(id),
    elegida_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE predefinidos (
    id         INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    usuario_id INTEGER NOT NULL REFERENCES usuarios(id),
    nombre     VARCHAR(100) NOT NULL,
    coste      INTEGER NOT NULL,
    efecto     VARCHAR(255) NOT NULL,
    UNIQUE (usuario_id, nombre)                    -- nombre único por usuario
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE eventos (
    id               INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    usuario_id       INTEGER NOT NULL REFERENCES usuarios(id),  -- quien recibe el efecto
    registrado_por_id INTEGER NOT NULL REFERENCES usuarios(id),
    predefinido_id   INTEGER REFERENCES predefinidos(id),       -- NULL si es evento libre
    nombre           VARCHAR(100) NOT NULL,
    fecha            DATE NOT NULL,
    coste            INTEGER NOT NULL,
    efecto           VARCHAR(255) NOT NULL,
    origen           VARCHAR(20) NOT NULL DEFAULT 'manual'
                     CHECK (origen IN ('manual', 'tienda', 'tarea', 'bono', 'planta')),
    anulado          BOOLEAN NOT NULL DEFAULT FALSE,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pareja_planta_cambios (
    id             INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    pareja_id      INTEGER NOT NULL REFERENCES parejas(id) ON DELETE CASCADE,
    usuario_id     INTEGER NOT NULL REFERENCES usuarios(id),
    planta_anterior VARCHAR(40),
    planta_nueva   VARCHAR(40) NOT NULL,
    coste          INTEGER NOT NULL DEFAULT 0,
    evento_id      INTEGER UNIQUE REFERENCES eventos(id),
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tienda_items (
    id            INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    clave         VARCHAR(40) UNIQUE,              -- solo para los ítems globales
    pareja_id     INTEGER REFERENCES parejas(id) ON DELETE CASCADE, -- NULL = global
    tipo          VARCHAR(20) NOT NULL
                  CHECK (tipo IN ('recompensa', 'permiso', 'ganancia')),
    nombre        VARCHAR(100) NOT NULL,
    descripcion   VARCHAR(255),
    coste         INTEGER NOT NULL CHECK (coste BETWEEN 1 AND 100),
    activo        BOOLEAN NOT NULL DEFAULT TRUE,
    creado_por_id INTEGER REFERENCES usuarios(id),
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (pareja_id IS NOT NULL OR clave IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_tienda_items_pareja
    ON tienda_items (pareja_id, activo);

CREATE TABLE pareja_tareas (
    id                INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    pareja_id         INTEGER NOT NULL REFERENCES parejas(id) ON DELETE CASCADE,
    item_id           INTEGER NOT NULL REFERENCES tienda_items(id),
    tipo              VARCHAR(20) NOT NULL
                      CHECK (tipo IN ('recompensa', 'permiso', 'ganancia')),
    titulo            VARCHAR(100) NOT NULL,
    coste             INTEGER NOT NULL CHECK (coste > 0),
    estado            VARCHAR(20) NOT NULL DEFAULT 'pendiente'
                      CHECK (estado IN ('pendiente', 'cumplida', 'rechazada', 'cancelada')),
    solicitante_id    INTEGER NOT NULL REFERENCES usuarios(id),
    destinatario_id   INTEGER NOT NULL REFERENCES usuarios(id),
    resuelto_por_id   INTEGER REFERENCES usuarios(id),
    evento_id         INTEGER UNIQUE REFERENCES eventos(id),
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resuelto_en       DATETIME,
    CHECK (solicitante_id <> destinatario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_pareja_tareas_pareja
    ON pareja_tareas (pareja_id, estado);

CREATE TABLE pareja_bonos (
    id          INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    pareja_id   INTEGER REFERENCES parejas(id) ON DELETE CASCADE,
    usuario_id  INTEGER NOT NULL REFERENCES usuarios(id),
    tipo        VARCHAR(20) NOT NULL
                CHECK (tipo IN ('cumpleanos', 'aniversario')),
    anio        INTEGER NOT NULL,
    importe     INTEGER NOT NULL CHECK (importe > 0),
    evento_id   INTEGER UNIQUE REFERENCES eventos(id),
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (usuario_id, tipo, anio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE recargas (
    id         INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    usuario_id INTEGER NOT NULL REFERENCES usuarios(id),
    importe    INTEGER NOT NULL,                   -- coins que se suman
    fecha      DATE NOT NULL,
    motivo     VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE configuracion (
    clave VARCHAR(50) PRIMARY KEY,
    valor VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- USUARIOS
INSERT INTO usuarios (nombre, email, password_hash, codigo, rol, saldo_inicial, fecha_nacimiento, activo) VALUES
('Noelia',  'noelia@example.com',  '$2b$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ01234', 'NOE001', 'admin',   100, '1990-03-12', TRUE),
('Daria',   'daria@example.com',   '$2b$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ01234', 'DAR002', 'miembro', 50,  '1994-11-24', TRUE),
('Carlos',  'carlos@example.com',  '$2b$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ01234', 'CAR003', 'miembro', NULL, '1992-07-08', TRUE),
('Lucia',   'lucia@example.com',   '$2b$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ01234', 'LUC004', 'miembro', 20,  '1995-02-28', FALSE);

-- PAREJAS (usuarios 1-2 y 3-4)
INSERT INTO parejas (usuario_1_id, usuario_2_id, fecha_aniversario) VALUES
(1, 2, '2025-02-14'),
(3, 4, '2024-06-21');

-- PREDEFINIDOS
INSERT INTO predefinidos (usuario_id, nombre, coste, efecto) VALUES
(1, 'Elegir película',     10, 'Elige la película de la noche'),
(1, 'Saltarse fregar',     15, 'No friega los platos hoy'),
(2, 'Elegir cena',         20, 'Elige qué se cena hoy'),
(2, 'Masaje',              30, 'Recibe un masaje de 15 minutos'),
(3, 'Dormir hasta tarde',  25, 'Duerme hasta tarde el domingo'),
(4, 'Elegir música',        5, 'Elige la música del coche');

-- TIENDA GLOBAL (pareja_id NULL = disponible para todas las parejas)
INSERT INTO tienda_items (clave, tipo, nombre, descripcion, coste, creado_por_id) VALUES
('pelicula',          'recompensa', 'Elegir película',        'Elige la película de la próxima noche',                 15, NULL),
('masaje',            'recompensa', 'Masaje de 15 minutos',   'Un masaje relajante de tu pareja',                      30, NULL),
('desayuno',          'recompensa', 'Desayuno en la cama',    'Desayuno preparado y servido por tu pareja',            40, NULL),
('plan_sorpresa',     'recompensa', 'Plan sorpresa',          'Tu pareja organiza un plan sin decirte cuál',           60, NULL),
('cena_especial',     'recompensa', 'Cena especial',          'Tu pareja cocina o encarga tu plato favorito',          80, NULL),
('elegir_cena',       'permiso',    'Elegir la cena',         'Elige qué se cena hoy en casa',                         20, NULL),
('saltarse_tarea',    'permiso',    'Saltarse una tarea',     'Saltarse una tarea del hogar sin penalización',         20, NULL),
('dormir_tarde',      'permiso',    'Dormir hasta tarde',     'Permiso para dormir hasta tarde un día del fin de semana', 25, NULL),
('dia_sin_pantallas', 'permiso',    'Día sin pantallas',      'Un día sin pantallas para los dos',                     35, NULL),
('ayuda_extra',       'ganancia',   'Ayuda extra en casa',    'Gana coins por una ayuda extra aprobada por tu pareja',  25, NULL),
('premio_sorpresa',   'ganancia',   'Premio sorpresa',        'Tu pareja te premia con coins por un detalle',          50, NULL)
ON DUPLICATE KEY UPDATE clave = clave;

-- EVENTOS (predefinido_id NULL = evento libre)
INSERT INTO eventos (usuario_id, registrado_por_id, predefinido_id, nombre, fecha, coste, efecto, anulado) VALUES
(1, 1, 1,    'Elegir película',    '2026-11-01', 10, 'Elige la película de la noche',   FALSE),
(1, 2, 2,    'Saltarse fregar',    '2026-11-02', 15, 'No friega los platos hoy',        FALSE),
(2, 2, 3,    'Elegir cena',        '2026-11-03', 20, 'Elige qué se cena hoy',           FALSE),
(2, 1, 4,    'Masaje',             '2026-11-04', 30, 'Recibe un masaje de 15 minutos',  TRUE),
(3, 3, 5,    'Dormir hasta tarde', '2026-11-05', 25, 'Duerme hasta tarde el domingo',   FALSE),
(4, 3, NULL, 'Plan sorpresa',      '2026-11-06', 40, 'Organiza un plan sorpresa',       FALSE);

-- RECARGAS
INSERT INTO recargas (usuario_id, importe, fecha, motivo) VALUES
(1, 50, '2026-11-01', 'Recarga mensual'),
(2, 50, '2026-11-01', 'Recarga mensual'),
(3, 30, '2026-11-02', 'Premio por ayudar en casa'),
(4, 20, '2026-11-03', NULL);

-- CONFIGURACION
INSERT INTO configuracion (clave, valor) VALUES
('recarga_mensual',  '50'),
('saldo_minimo',     '0'),
('nombre_app',       'FamilyCoins');
