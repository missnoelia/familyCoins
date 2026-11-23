# Diseño de la base de datos

Base de datos de CoupleCoins. El script para crearla está en [database.sql](database.sql).

## Tablas

### usuarios
Personas que usan la aplicación.

| Campo | Tipo | Notas |
|-------|------|-------|
| id    | SERIAL | Clave primaria |
| nombre | VARCHAR(100) | Obligatorio |
| email | VARCHAR(150) | Obligatorio, único |
| password_hash | VARCHAR(255) | Contraseña cifrada |
| codigo | VARCHAR(20) | Obligatorio, único |
| rol   | VARCHAR(30) | Obligatorio |
| saldo_inicial | INTEGER | Puede ser NULL |
| activo | BOOLEAN | Por defecto TRUE |
| created_at | TIMESTAMP | Por defecto la fecha actual |

### parejas
Une a dos usuarios en una pareja.

| Campo | Tipo | Notas |
|-------|------|---|
| id    | SERIAL | Clave primaria |
| usuario_1_id | INTEGER | FK → usuarios(id). Único: un usuario solo puede estar en una pareja |
| usuario_2_id | INTEGER | FK → usuarios(id). Único |
| created_at | TIMESTAMP | Por defecto la fecha actual |

Restricción: `usuario_1_id` y `usuario_2_id` no pueden ser el mismo usuario.

### predefinidos
Eventos que cada usuario deja guardados para reutilizarlos.

| Campo     | Tipo | Notas |
|-----------|------|-------|
| id        | SERIAL | Clave primaria |
| usuario_id| INTEGER | FK → usuarios(id) |
| nombre    | VARCHAR(100) | Obligatorio |
| coste     | INTEGER | Obligatorio |
| efecto    | VARCHAR(255) | Obligatorio |

Restricción: el `nombre` es único por usuario (`usuario_id` + `nombre`).

### eventos
Registro de cada evento que ocurre.

|     Campo         |    Tipo    | Notas |
|-------------------|------------|-------|
| id                | SERIAL | Clave primaria |
| usuario_id        | INTEGER | FK → usuarios(id). Es quien recibe el efecto |
| registrado_por_id | INTEGER | FK → usuarios(id). Es quien registra el evento |
| predefinido_id    | INTEGER | FK → predefinidos(id). NULL si es un evento libre |
| nombre            | VARCHAR(100) | Obligatorio |
| fecha             | DATE      | Obligatorio |
| coste             | INTEGER   | Obligatorio |
| efecto            | VARCHAR(255) | Obligatorio |
| anulado           | BOOLEAN   | Por defecto FALSE |
| created_at        | TIMESTAMP | Por defecto la fecha actual |

### recargas
Suma coins a una persona.

| Campo | Tipo | Notas |
|---|---|---|
| id         | SERIAL       | Clave primaria |
| usuario_id | INTEGER      | FK → usuarios(id) |
| importe    | INTEGER       | Coins que se suman |
| fec        | DATE          | Obligatorio |
| motivo     | VARCHAR(255)  | Opcional |

### configuracion
Parámetros generales de la aplicación.

| Campo | Tipo | Notas |
|-------|------|-------|
| clave | VARCHAR(50) | Clave primaria |
| valor | VARCHAR(255) | Obligatorio |

## Relaciones

- Un usuario puede tener muchos predefinidos, eventos y recargas.
- Una pareja une exactamente a 2 usuarios, y cada usuario solo puede estar en una pareja.
- Un evento puede venir de un predefinido (`predefinido_id`) o ser libre (NULL).
- En `eventos`, `usuario_id` es quien recibe el efecto y `registrado_por_id` es quien lo apunta.
- Los eventos no se borran: se marcan como anulados con `anulado = TRUE`.