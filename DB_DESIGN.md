# Diseño de la base de datos

Base de datos de FamilyCoins para MySQL/MariaDB. El script para crearla está en
[db/schema.sql](db/schema.sql).

Todas las tablas usan el motor `InnoDB` y `utf8mb4` con collation
`utf8mb4_unicode_ci`. Los identificadores son `INT AUTO_INCREMENT` y las marcas
de tiempo son `DATETIME DEFAULT CURRENT_TIMESTAMP`.

## Tablas

### usuarios

Personas que usan la aplicación.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | INT AUTO_INCREMENT | Clave primaria |
| nombre | VARCHAR(100) | Obligatorio |
| email | VARCHAR(150) | Obligatorio, único |
| password_hash | VARCHAR(255) | Contraseña cifrada |
| codigo | VARCHAR(20) | Obligatorio, único |
| rol | VARCHAR(30) | Obligatorio (`usuario` o `superadmin`) |
| saldo_inicial | INTEGER | Puede ser NULL |
| fecha_nacimiento | DATE | Cumpleaños; puede ser NULL |
| activo | BOOLEAN | Por defecto TRUE |
| created_at | DATETIME | Por defecto la fecha actual |

### parejas

Une a dos usuarios en una pareja.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | INT AUTO_INCREMENT | Clave primaria |
| usuario_1_id | INTEGER | FK → usuarios(id). Único: un usuario solo puede estar en una pareja |
| usuario_2_id | INTEGER | FK → usuarios(id). Único |
| fecha_aniversario | DATE | Aniversario de la pareja; puede ser NULL |
| created_at | DATETIME | Por defecto la fecha actual |

Restricción: `usuario_1_id` y `usuario_2_id` no pueden ser el mismo usuario.

### pareja_streaks

Contador de racha compartida por pareja.

| Campo | Tipo | Notas |
|-------|------|-------|
| pareja_id | INTEGER | Clave primaria y FK → parejas(id) |
| racha_actual | INTEGER | Por defecto 0 |
| racha_maxima | INTEGER | Por defecto 0 |
| ultimo_dia_completo | DATE | Último día en que entraron los dos |
| updated_at | DATETIME | Por defecto la fecha actual |

### pareja_streak_checkins

Entradas diarias de cada miembro. La clave primaria impide duplicados.

| Campo | Tipo | Notas |
|-------|------|-------|
| pareja_id | INTEGER | FK → parejas(id) |
| usuario_id | INTEGER | FK → usuarios(id) |
| fecha | DATE | Día de la entrada |
| created_at | DATETIME | Por defecto la fecha actual |

Clave primaria compuesta: `(pareja_id, usuario_id, fecha)`. Índice adicional
`idx_streak_checkins_fecha` sobre `(pareja_id, fecha)`.

### pareja_plantas

Planta compartida de la pareja.

| Campo | Tipo | Notas |
|-------|------|-------|
| pareja_id | INTEGER | Clave primaria y FK → parejas(id) |
| planta_id | VARCHAR(40) | Especie elegida |
| etapa | INTEGER | Etapa actual (0–4) |
| etapa_maxima | INTEGER | Mejor etapa alcanzada (0–4) |
| progreso_dias | INTEGER | Días de crecimiento en la etapa actual |
| degradada_para | DATE | Último día completo ya penalizado |
| reiniciada_para | DATE | Último día completo que provocó el reinicio |
| elegida_por_id | INTEGER | FK → usuarios(id) |
| elegida_en | DATETIME | Fecha de la elección |
| updated_at | DATETIME | Por defecto la fecha actual |

### predefinidos

Eventos que cada usuario deja guardados para reutilizarlos.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | INT AUTO_INCREMENT | Clave primaria |
| usuario_id | INTEGER | FK → usuarios(id) |
| nombre | VARCHAR(100) | Obligatorio |
| coste | INTEGER | Obligatorio |
| efecto | VARCHAR(255) | Obligatorio |

Restricción: el `nombre` es único por usuario (`usuario_id` + `nombre`).

### eventos

Registro de cada evento que ocurre.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | INT AUTO_INCREMENT | Clave primaria |
| usuario_id | INTEGER | FK → usuarios(id). Es quien recibe el efecto |
| registrado_por_id | INTEGER | FK → usuarios(id). Es quien registra el evento |
| predefinido_id | INTEGER | FK → predefinidos(id). NULL si es un evento libre |
| nombre | VARCHAR(100) | Obligatorio |
| fecha | DATE | Obligatorio |
| coste | INTEGER | Obligatorio |
| efecto | VARCHAR(255) | Obligatorio |
| origen | VARCHAR(20) | `manual`, `tienda`, `tarea`, `bono` o `planta` |
| anulado | BOOLEAN | Por defecto FALSE |
| created_at | DATETIME | Por defecto la fecha actual |

### pareja_planta_cambios

Histórico de cambios de planta.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | INT AUTO_INCREMENT | Clave primaria |
| pareja_id | INTEGER | FK → parejas(id) |
| usuario_id | INTEGER | FK → usuarios(id) |
| planta_anterior | VARCHAR(40) | Puede ser NULL |
| planta_nueva | VARCHAR(40) | Obligatorio |
| coste | INTEGER | Por defecto 0 |
| evento_id | INTEGER | FK → eventos(id), único |
| created_at | DATETIME | Por defecto la fecha actual |

### tienda_items

Artículos de la tienda. Los globales tienen `pareja_id NULL` y una `clave`; los
personalizados tienen `pareja_id`.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | INT AUTO_INCREMENT | Clave primaria |
| clave | VARCHAR(40) | Único; solo para los ítems globales |
| pareja_id | INTEGER | FK → parejas(id). NULL = global |
| tipo | VARCHAR(20) | `recompensa`, `permiso` o `ganancia` |
| nombre | VARCHAR(100) | Obligatorio |
| descripcion | VARCHAR(255) | Opcional |
| coste | INTEGER | Entre 1 y 100 |
| activo | BOOLEAN | Por defecto TRUE |
| creado_por_id | INTEGER | FK → usuarios(id) |
| created_at | DATETIME | Por defecto la fecha actual |

Restricción: `pareja_id` o `clave` deben estar informados. Índice
`idx_tienda_items_pareja` sobre `(pareja_id, activo)`.

### pareja_tareas

Compras y tareas pendientes de confirmación por la pareja.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | INT AUTO_INCREMENT | Clave primaria |
| pareja_id | INTEGER | FK → parejas(id) |
| item_id | INTEGER | FK → tienda_items(id) |
| tipo | VARCHAR(20) | `recompensa`, `permiso` o `ganancia` |
| titulo | VARCHAR(100) | Obligatorio |
| coste | INTEGER | Mayor que 0 |
| estado | VARCHAR(20) | `pendiente`, `cumplida`, `rechazada` o `cancelada` |
| solicitante_id | INTEGER | FK → usuarios(id) |
| destinatario_id | INTEGER | FK → usuarios(id) |
| resuelto_por_id | INTEGER | FK → usuarios(id) |
| evento_id | INTEGER | FK → eventos(id), único |
| created_at | DATETIME | Por defecto la fecha actual |
| resuelto_en | DATETIME | Puede ser NULL |

Restricciones: `solicitante_id` y `destinatario_id` deben ser distintos. Índice
`idx_pareja_tareas_pareja` sobre `(pareja_id, estado)`.

### pareja_bonos

Bonos automáticos ya entregados.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | INT AUTO_INCREMENT | Clave primaria |
| pareja_id | INTEGER | FK → parejas(id) |
| usuario_id | INTEGER | FK → usuarios(id) |
| tipo | VARCHAR(20) | `cumpleanos` o `aniversario` |
| anio | INTEGER | Año del bono |
| importe | INTEGER | Mayor que 0 |
| evento_id | INTEGER | FK → eventos(id), único |
| created_at | DATETIME | Por defecto la fecha actual |

Restricción única `(usuario_id, tipo, anio)`: cada bono se entrega una sola vez
por persona, tipo y año.

### recargas

Suma coins a una persona.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | INT AUTO_INCREMENT | Clave primaria |
| usuario_id | INTEGER | FK → usuarios(id) |
| importe | INTEGER | Coins que se suman |
| fecha | DATE | Obligatorio |
| motivo | VARCHAR(255) | Opcional |

### configuracion

Parámetros generales de la aplicación.

| Campo | Tipo | Notas |
|-------|------|-------|
| clave | VARCHAR(50) | Clave primaria |
| valor | VARCHAR(255) | Obligatorio |

## Relaciones

- Un usuario puede tener muchos predefinidos, eventos y recargas.
- Una pareja une exactamente a 2 usuarios, y cada usuario solo puede estar en una
  pareja.
- Un evento puede venir de un predefinido (`predefinido_id`) o ser libre (NULL).
- En `eventos`, `usuario_id` es quien recibe el efecto y `registrado_por_id` es
  quien lo apunta.
- Los eventos no se borran: se marcan como anulados con `anulado = TRUE`.
- Cada pareja tiene una fila en `pareja_streaks`, una en `pareja_plantas` y
  muchas en `pareja_streak_checkins`, `pareja_planta_cambios`, `pareja_tareas` y
  `pareja_bonos`.
- Los artículos globales de la tienda (`pareja_id NULL`) son visibles para todas
  las parejas; los personalizados solo para la suya.
- Los bonos referencian el evento `suma` que generaron y no se repiten gracias a
  la clave única `(usuario_id, tipo, anio)`.
