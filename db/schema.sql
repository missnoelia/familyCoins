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