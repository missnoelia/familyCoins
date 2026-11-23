CREATE TABLE usuarios (
    id             SERIAL PRIMARY KEY,
    nombre         VARCHAR(100) NOT NULL,
    email          VARCHAR(150) NOT NULL UNIQUE,
    password_hash  VARCHAR(255) NOT NULL,
    codigo         VARCHAR(20)  NOT NULL UNIQUE,
    rol            VARCHAR(30)  NOT NULL,
    saldo_inicial  INTEGER,                        -- puede ser NULL
    activo         BOOLEAN NOT NULL DEFAULT TRUE,
    created_at     TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE parejas (
    id           SERIAL PRIMARY KEY,
    usuario_1_id INTEGER NOT NULL UNIQUE REFERENCES usuarios(id),
    usuario_2_id INTEGER NOT NULL UNIQUE REFERENCES usuarios(id),
    created_at   TIMESTAMP NOT NULL DEFAULT NOW(),
    CHECK (usuario_1_id <> usuario_2_id)
);

CREATE TABLE predefinidos (
    id         SERIAL PRIMARY KEY,
    usuario_id INTEGER NOT NULL REFERENCES usuarios(id),
    nombre     VARCHAR(100) NOT NULL,
    coste      INTEGER NOT NULL,
    efecto     VARCHAR(255) NOT NULL,
    UNIQUE (usuario_id, nombre)                    -- nombre único por usuario
);

CREATE TABLE eventos (
    id               SERIAL PRIMARY KEY,
    usuario_id       INTEGER NOT NULL REFERENCES usuarios(id),  -- quien recibe el efecto
    registrado_por_id INTEGER NOT NULL REFERENCES usuarios(id),
    predefinido_id   INTEGER REFERENCES predefinidos(id),       -- NULL si es evento libre
    nombre           VARCHAR(100) NOT NULL,
    fecha            DATE NOT NULL,
    coste            INTEGER NOT NULL,
    efecto           VARCHAR(255) NOT NULL,
    anulado          BOOLEAN NOT NULL DEFAULT FALSE,
    created_at       TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE recargas (
    id         SERIAL PRIMARY KEY,
    usuario_id INTEGER NOT NULL REFERENCES usuarios(id),
    importe    INTEGER NOT NULL,                   -- coins que se suman
    fecha      DATE NOT NULL,
    motivo     VARCHAR(255)
);

CREATE TABLE configuracion (
    clave VARCHAR(50) PRIMARY KEY,
    valor VARCHAR(255) NOT NULL
);

-- USUARIOS
INSERT INTO usuarios (nombre, email, password_hash, codigo, rol, saldo_inicial, activo) VALUES
('Noelia',  'noelia@example.com',  '$2b$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ01234', 'NOE001', 'admin',   100,  TRUE),
('Daria',   'daria@example.com',   '$2b$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ01234', 'DAR002', 'miembro', 50,   TRUE),
('Carlos',  'carlos@example.com',  '$2b$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ01234', 'CAR003', 'miembro', NULL, TRUE),
('Lucia',   'lucia@example.com',   '$2b$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ01234', 'LUC004', 'miembro', 20,   FALSE);

-- PAREJAS (usuarios 1-2 y 3-4)
INSERT INTO parejas (usuario_1_id, usuario_2_id) VALUES
(1, 2),
(3, 4);

-- PREDEFINIDOS
INSERT INTO predefinidos (usuario_id, nombre, coste, efecto) VALUES
(1, 'Elegir película',     10, 'Elige la película de la noche'),
(1, 'Saltarse fregar',     15, 'No friega los platos hoy'),
(2, 'Elegir cena',         20, 'Elige qué se cena hoy'),
(2, 'Masaje',              30, 'Recibe un masaje de 15 minutos'),
(3, 'Dormir hasta tarde',  25, 'Duerme hasta tarde el domingo'),
(4, 'Elegir música',        5, 'Elige la música del coche');

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