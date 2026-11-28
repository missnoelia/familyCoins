# FamilyCoinsApp – Documentación de la API

## 1. Introducción

FamilyCoinsApp es una aplicación para que una pareja controle sus FamilyCoins.
Cada evento pertenece a una persona y tiene un efecto:

- **resta**: el coste se descuenta del saldo de esa persona.
- **suma**: el coste se suma al saldo de su pareja.

El Backend está desarrollado en **PHP 8** y utiliza **MySQL/MariaDB**. La
comunicación con el Frontend se hace mediante una API REST que devuelve siempre
JSON.

Alcance implementado: **sprints 1 a 6** y las ampliaciones posteriores de
recuerdos, logros y dos plantas por pareja. Incluye saldos, estadísticas por
periodo, administración de usuarios y parejas, cambio de contraseña, un endpoint
de salud, la racha compartida de pareja, una planta por cada miembro que crece
con la racha, la tienda de pareja con tareas confirmadas, los bonos automáticos
por cumpleaños y aniversario, los recuerdos compartidos y los logros calculados
con datos reales. Los endpoints de administración requieren rol `superadmin`.

## 2. Configuración general

- **Base URL:** `/api/`
- **Formato de respuesta:** JSON (`Content-Type: application/json`).
- **Métodos:** `GET` para consultas y `POST` para altas, modificaciones y bajas.
- **Autenticación:** sesiones PHP.
- **Base de datos:** MySQL/MariaDB, mediante PDO (`pdo_mysql`).
- **Moneda única:** las compras de la tienda, las tareas, el cambio de planta y
  los bonos usan las mismas **coins** del saldo. No hay puntos separados.
- **Zona horaria:** las fechas se envían y se devuelven como `YYYY-MM-DD`. La
  racha, la planta y los bonos usan `Europe/Madrid` por defecto, configurable
  con `STREAK_TIMEZONE`.

Los endpoints privados necesitan una sesión activa. Salvo las rutas de
autenticación y vinculación de pareja, también exigen que el usuario tenga una
pareja vinculada.

Los endpoints bajo `/api/admin_*` exigen además el rol `superadmin`. Todas las
rutas de escritura usan `POST`; las de consulta usan `GET`. Los listados de
administración aceptan `pagina` (por defecto 1) y `limite` (por defecto 50,
máximo 200).

## 3. Formato de respuestas

### Respuesta correcta

```json
{
    "success": true,
    "message": "Operación realizada correctamente.",
    "data": {}
}
```

### Respuesta de error

```json
{
    "success": false,
    "message": "Descripción del error."
}
```

### Códigos HTTP

| Código | Significado |
|---|---|
| 200 | Operación correcta |
| 201 | Recurso creado |
| 400 | Datos incorrectos |
| 401 | Usuario no autenticado o cuenta inactiva |
| 403 | Acceso denegado o pareja no vinculada |
| 404 | Recurso no encontrado |
| 405 | Método HTTP no permitido |
| 409 | Conflicto o recurso duplicado |
| 500 | Error interno del servidor |

## 4. Autenticación

### POST `/api/register.php`

Registra un usuario y genera su código personal de 3 letras y 4 dígitos.

**Entrada:**

```json
{
    "nombre": "Daria",
    "email": "daria@example.com",
    "password": "Password123",
    "fecha_nacimiento": "1994-11-24"
}
```

**Respuesta correcta (201):**

```json
{
    "success": true,
    "message": "Usuario registrado correctamente.",
    "data": {
        "id": 1,
        "nombre": "Daria",
        "email": "daria@example.com",
        "codigo": "ABC1234",
        "fecha_nacimiento": "1994-11-24"
    }
}
```

**Validaciones:**

- Nombre obligatorio, máximo 100 caracteres.
- Email válido, máximo 150 caracteres y no registrado previamente.
- Contraseña de al menos 4 caracteres y con una mayúscula.
- `fecha_nacimiento` es opcional; si se envía, debe ser una fecha real
  `YYYY-MM-DD`. Se usa para el bono automático de cumpleaños.
- El código se genera automáticamente y se reintenta si colisiona.

### POST `/api/login.php`

Comprueba las credenciales e inicia la sesión.

**Entrada:**

```json
{
    "email": "daria@example.com",
    "password": "Password123"
}
```

**Respuesta correcta (200):**

```json
{
    "success": true,
    "message": "Sesión iniciada correctamente.",
    "data": {
        "id": 1,
        "nombre": "Daria",
        "email": "daria@example.com",
        "codigo": "ABC1234",
        "rol": "usuario",
        "tiene_pareja": true,
        "pareja": {
            "id": 2,
            "nombre": "Alex",
            "email": "alex@example.com",
            "codigo": "XYZ5678",
            "rol": "usuario",
            "fecha_nacimiento": "1992-03-08"
        }
    }
}
```

La contraseña se comprueba con `password_verify()`. Si el email no existe, la
cuenta está inactiva o la contraseña no coincide, se devuelve `401` con un
mensaje genérico.

### POST `/api/logout.php`

Cierra la sesión actual. No requiere estar autenticado.

**Respuesta correcta:**

```json
{
    "success": true,
    "message": "Sesión cerrada correctamente."
}
```

### GET `/api/check_login.php`

Indica si hay sesión activa, si el usuario tiene pareja, el estado de la racha
compartida, las dos plantas de la pareja y los bonos entregados en esta llamada.
Con sesión y pareja, esta llamada registra automáticamente la entrada del día en
la racha y procesa los bonos de cumpleaños y aniversario.

**Respuesta sin sesión (200):**

```json
{
    "success": true,
    "data": {
        "logged": false,
        "usuario": null,
        "tiene_pareja": false,
        "pareja": null,
        "pareja_info": null,
        "streak": null,
        "planta": null,
        "bonos": []
    }
}
```

**Respuesta con sesión (200):**

```json
{
    "success": true,
    "data": {
        "logged": true,
        "usuario": {
            "id": 1,
            "nombre": "Daria",
            "email": "daria@example.com",
            "codigo": "ABC1234",
            "rol": "usuario",
            "saldo_inicial": null,
            "fecha_nacimiento": "1994-11-24"
        },
        "tiene_pareja": true,
        "pareja": {
            "id": 2,
            "nombre": "Alex",
            "email": "alex@example.com",
            "codigo": "XYZ5678",
            "rol": "usuario",
            "fecha_nacimiento": "1992-03-08"
        },
        "pareja_info": {
            "id": 1,
            "usuario_1_id": 1,
            "usuario_2_id": 2,
            "fecha_aniversario": "2025-02-14"
        },
        "streak": {
            "pareja_id": 1,
            "racha_actual": 3,
            "racha_maxima": 5,
            "ultimo_dia_completo": "2026-11-23",
            "fecha": "2026-11-24",
            "completado_hoy": true,
            "yo_he_entrado": true,
            "pareja_ha_entrado": true,
            "registrada": false,
            "completado_ahora": false
        },
        "planta": {
            "pareja_id": 1,
            "fecha": "2026-11-24",
            "catalogo": [
                { "id": "monstera", "nombre": "Monstera", "descripcion": "Hojas grandes y exuberantes para una pareja que crece junta." }
            ],
            "etapas": [
                { "id": "semilla", "nombre": "Semilla" }
            ],
            "umbrales": [3, 4, 7, 16],
            "coste_cambio": 20,
            "planta": null
        },
        "bonos": []
    }
}
```

Los arrays `catalogo` y `etapas` del ejemplo están abreviados; su contenido
completo se documenta en la sección de las plantas de la pareja. El objeto
`planta` de `check_login.php` es el mismo estado que devuelve `planta.php`, con
`mi_planta` y el array `plantas` para los dos miembros.

Si la cuenta fue dada de baja mientras la sesión estaba abierta, se cierra la
sesión y se devuelve `logged: false`. Si el usuario no tiene pareja, `streak`,
`pareja_info` y `planta` son `null`, `bonos` está vacío y no se registra ninguna
entrada.

`bonos` contiene solo los bonos creados en esta llamada concreta. En llamadas
posteriores del mismo año el array vuelve a estar vacío, aunque los eventos ya
entregados siguen contando en el saldo y en las estadísticas.

## 5. Parejas

### GET `/api/mi_codigo.php`

Devuelve el código de vinculación del usuario autenticado.

```json
{
    "success": true,
    "data": {
        "codigo": "ABC1234"
    }
}
```

### POST `/api/pareja_vincular.php`

Vincula al usuario autenticado con otra cuenta mediante su código.

**Entrada:**

```json
{
    "codigo": "XYZ5678"
}
```

**Respuesta correcta (200):**

```json
{
    "success": true,
    "message": "Pareja vinculada correctamente.",
    "data": {
        "pareja_id": 1,
        "fecha_aniversario": "2026-11-25",
        "usuario": {
            "id": 2,
            "nombre": "Alex",
            "codigo": "XYZ5678"
        }
    }
}
```

**Validaciones:**

- El código debe tener el formato `AAA0000` (3 letras y 4 dígitos).
- El código debe existir y pertenecer a un usuario activo.
- El usuario no puede vincularse consigo mismo.
- Ninguno de los dos usuarios puede tener ya una pareja (`409`).
- La vinculación se hace en una transacción con bloqueo de filas.
- `fecha_aniversario` se inicializa con la fecha de la vinculación; después se
  puede cambiar con `POST /api/pareja_aniversario.php`.

### GET `/api/pareja_estado.php`

Devuelve la pareja del usuario autenticado.

```json
{
    "success": true,
    "data": {
        "pareja": {
            "id": 2,
            "nombre": "Alex",
            "email": "alex@example.com",
            "codigo": "XYZ5678",
            "rol": "usuario",
            "fecha_nacimiento": "1992-03-08"
        },
        "pareja_info": {
            "id": 1,
            "usuario_1_id": 1,
            "usuario_2_id": 2,
            "fecha_aniversario": "2025-02-14"
        }
    }
}
```

Si no tiene pareja, `pareja` y `pareja_info` son `null`.

## 6. Racha de pareja

La racha es un contador compartido por la pareja. Cada miembro registra su
entrada diaria y el día solo cuenta cuando **los dos** han entrado ese mismo
día. Repetir la entrada el mismo día no duplica nada.

- El día se calcula en el backend con la zona horaria `Europe/Madrid`
  (configurable con la variable de entorno `STREAK_TIMEZONE`), nunca con la
  fecha del navegador.
- Al completar el día, si el día anterior también se había completado, la racha
  sube una unidad.
- Si se salta uno o más días, al completar el nuevo día la racha vuelve a `1`.
- `racha_maxima` guarda el mejor valor histórico de la pareja.
- Completar un día también suma un día de crecimiento a las dos plantas de la
  pareja (ver sección 8). Consultar la racha aplica antes cualquier rotura o
  reinicio pendiente de las plantas.
- Si el último día completo no es hoy ni ayer, la consulta devuelve
  `racha_actual: 0` aunque todavía no se haya completado un día nuevo.
- Requiere tener pareja: si no la hay, se devuelve `403`.
- Abrir la app a través de `check_login.php` registra automáticamente la
  entrada del día; `POST /api/streak_checkin.php` permite forzarla
  explícitamente.

### GET `/api/streak.php`

Devuelve el estado de la racha para el usuario autenticado.

```json
{
    "success": true,
    "data": {
        "pareja_id": 1,
        "racha_actual": 3,
        "racha_maxima": 5,
        "ultimo_dia_completo": "2026-11-23",
        "fecha": "2026-11-24",
        "completado_hoy": false,
        "yo_he_entrado": true,
        "pareja_ha_entrado": false
    }
}
```

### POST `/api/streak_checkin.php`

Registra la entrada de hoy del usuario autenticado. Es idempotente: si ya había
entrado hoy, no se crea otro registro. No necesita cuerpo. La respuesta incluye
`registrada` (si esta llamada creó la entrada) y `completado_ahora` (si esta
llamada completó el día y sumó la racha).

```json
{
    "success": true,
    "message": "Entrada registrada. Falta que tu pareja entre hoy.",
    "data": {
        "pareja_id": 1,
        "racha_actual": 0,
        "racha_maxima": 0,
        "ultimo_dia_completo": null,
        "fecha": "2026-11-24",
        "completado_hoy": false,
        "yo_he_entrado": true,
        "pareja_ha_entrado": false,
        "registrada": true,
        "completado_ahora": false
    }
}
```

## 7. Perfil y fechas especiales

El perfil guarda la fecha de nacimiento del usuario y el aniversario de la
pareja. Ambas fechas se usan para los bonos automáticos de la sección 10.

### GET `/api/perfil.php`

Requiere sesión, pero no pareja. Devuelve el usuario autenticado, los datos
públicos de su pareja (`pareja`, o `null`) y la fila de la relación
(`pareja_info`, con `fecha_aniversario`).

```json
{
    "success": true,
    "data": {
        "usuario": {
            "id": 1,
            "nombre": "Daria",
            "email": "daria@example.com",
            "codigo": "ABC1234",
            "rol": "usuario",
            "saldo_inicial": null,
            "fecha_nacimiento": "1994-11-24",
            "activo": true,
            "created_at": "2026-11-01 10:00:00"
        },
        "pareja": {
            "id": 2,
            "nombre": "Alex",
            "email": "alex@example.com",
            "codigo": "XYZ5678",
            "rol": "usuario",
            "fecha_nacimiento": "1992-03-08"
        },
        "pareja_info": {
            "id": 1,
            "usuario_1_id": 1,
            "usuario_2_id": 2,
            "fecha_aniversario": "2025-02-14"
        }
    }
}
```

### POST `/api/perfil_fecha_nacimiento.php`

Actualiza la fecha de nacimiento del usuario autenticado. Requiere sesión, pero
no pareja.

```json
{
    "fecha_nacimiento": "1994-11-24"
}
```

- La fecha debe ser real y estar en formato `YYYY-MM-DD`.
- Enviar `null` o una cadena vacía borra la fecha.
- Devuelve el usuario actualizado en `data`.

### POST `/api/pareja_aniversario.php`

Actualiza la fecha de aniversario de la pareja. Requiere sesión y pareja.

```json
{
    "fecha_aniversario": "2025-02-14"
}
```

- La fecha debe ser real y estar en formato `YYYY-MM-DD`.
- Enviar `null` o una cadena vacía borra la fecha.
- Devuelve `id`, `usuario_1_id`, `usuario_2_id` y `fecha_aniversario` en `data`.
- Al vincular una pareja o crearla desde administración, la fecha se inicializa
  con el día de la vinculación.

## 8. Plantas de la pareja

Cada pareja tiene **dos plantas, una por cada miembro**. Cada persona elige y
cambia su propia especie. Las dos plantas crecen a la vez cuando la pareja
completa un día de racha y se degradan cuando la racha se rompe.

### Catálogo y etapas

El catálogo es fijo y tiene seis especies:

| `id` | Nombre |
|---|---|
| `monstera` | Monstera |
| `cactus` | Cactus |
| `helecho` | Helecho |
| `girasol` | Girasol |
| `cerezo` | Cerezo |
| `suculenta` | Suculenta |

Hay cinco etapas. Cada etapa necesita estos días de crecimiento para pasar a la
siguiente:

| Etapa | `id` | Días para la siguiente | Días acumulados |
|---|---|---|---|
| Semilla (0) | `semilla` | 3 | 3 |
| Brote (1) | `brote` | 4 | 7 |
| Joven (2) | `joven` | 7 | 14 |
| Fuerte (3) | `fuerte` | 16 | 30 |
| Floración (4) | `floracion` | — | 30 |

### Reglas de crecimiento

- Cada día de racha completado suma **un día de crecimiento** a **cada una** de
  las dos plantas.
- Cada planta avanza por su cuenta: conserva su especie, su etapa y su progreso.
  Al llegar al umbral de la etapa actual, la planta sube de etapa y el progreso
  continúa con el sobrante.
- En floración (etapa 4) el progreso se queda a cero y esa planta ya no crece más.
- Si pasan **2 o más días** sin completar la racha, cada planta pierde exactamente
  **una etapa** y su progreso vuelve a cero. La pérdida se aplica una sola vez
  por cada último día completo.
- Si pasan **4 o más días** sin completar la racha, cada planta vuelve a semilla y
  el progreso se pone a cero, pero se conserva `etapa_maxima` como histórico.
  El reinicio tiene prioridad sobre la pérdida de una etapa.
- La primera planta de cada miembro es gratuita. Cambiar la propia planta cuesta
  **20 coins**, se paga con el saldo de esa persona y conserva su etapa y su
  progreso.
- El cambio de planta crea un evento `resta` de origen `planta`, por lo que
  aparece en el historial y en las estadísticas.

### GET `/api/planta.php`

Requiere sesión y pareja. Devuelve el catálogo, las etapas, los umbrales y las
dos plantas de la pareja. `mi_planta` es la planta del usuario autenticado y
`plantas` incluye a los dos miembros con `usuario_id`, `nombre_usuario`,
`es_mia` y `planta`. Si un miembro todavía no ha elegido planta, su `planta` es
`null`. El campo `planta` se mantiene como alias de `mi_planta` para no romper a
los clientes anteriores.

```json
{
    "success": true,
    "data": {
        "pareja_id": 1,
        "fecha": "2026-11-25",
        "catalogo": [
            {
                "id": "monstera",
                "nombre": "Monstera",
                "descripcion": "Hojas grandes y exuberantes para una pareja que crece junta."
            }
        ],
        "etapas": [
            { "id": "semilla", "nombre": "Semilla" }
        ],
        "umbrales": [3, 4, 7, 16],
        "coste_cambio": 20,
        "planta": {
            "planta_id": "monstera",
            "nombre": "Monstera",
            "etapa": 2,
            "etapa_id": "joven",
            "progreso_dias": 2
        },
        "mi_planta": {
            "planta_id": "monstera",
            "nombre": "Monstera",
            "etapa": 2,
            "etapa_id": "joven",
            "progreso_dias": 2
        },
        "plantas": [
            {
                "usuario_id": 1,
                "nombre_usuario": "Daria",
                "es_mia": true,
                "planta": {
                    "planta_id": "monstera",
                    "nombre": "Monstera",
                    "descripcion": "Hojas grandes y exuberantes para una pareja que crece junta.",
                    "etapa": 2,
                    "etapa_id": "joven",
                    "etapa_nombre": "Joven",
                    "etapa_maxima": 2,
                    "etapa_maxima_id": "joven",
                    "progreso_dias": 2,
                    "dias_para_siguiente": 5,
                    "progreso_etapa": 28,
                    "completada": false,
                    "elegida_por_id": 1,
                    "elegida_en": "2026-11-10 19:30:00",
                    "updated_at": "2026-11-25 09:00:00"
                }
            },
            {
                "usuario_id": 2,
                "nombre_usuario": "Alex",
                "es_mia": false,
                "planta": null
            }
        ]
    }
}
```

Los arrays `catalogo` y `etapas` del ejemplo están abreviados. `planta` y
`mi_planta` contienen el mismo objeto que `plantas[n].planta` del usuario
autenticado. `progreso_etapa` es el porcentaje entero de avance dentro de la
etapa actual.

### POST `/api/planta_elegir.php`

Elige la primera planta del usuario autenticado o cambia la suya.

```json
{
    "planta_id": "cactus"
}
```

- `planta_id` debe existir en el catálogo; si no, `400`.
- La primera elección de cada miembro es gratuita y empieza en semilla.
- Elegir la misma planta no tiene coste ni modifica el progreso.
- Cambiar de planta cuesta 20 coins y se descuenta del saldo del usuario que
  hace el cambio. Si no hay saldo suficiente: `409`.
- El cambio conserva la etapa y el progreso de esa persona.
- La respuesta incluye el estado completo en `data` y el coste aplicado en
  `coste` (`0` o `20`).

## 9. Tienda y tareas

La tienda es compartida por la pareja. Incluye un catálogo global, igual para
todas las parejas, y hasta **15 artículos personalizados por pareja**. El coste
de cualquier artículo debe estar entre **1 y 100 coins**.

Tipos de artículo:

- `recompensa`: un beneficio que la pareja proporciona (por ejemplo, una cena o
  un masaje).
- `permiso`: un permiso especial (por ejemplo, elegir la cena o saltarse una
  tarea).
- `ganancia`: una tarea que hace ganar coins al solicitante cuando la pareja la
  confirma.

Las compras de `recompensa` y `permiso` se cobran al solicitante en el momento
de crear la tarea, con un evento `resta` de origen `tienda`. Las de `ganancia`
no cobran nada al crearse: al confirmar la tarea se crea un evento `suma` de
origen `tarea` que acredita las coins al solicitante. Todos estos movimientos
aparecen en el historial de eventos y en las estadísticas.

### GET `/api/tienda.php`

Requiere sesión y pareja. Devuelve los artículos activos visibles para la pareja
(los globales y los propios), el número de artículos personalizados y los
límites del sistema.

```json
{
    "success": true,
    "data": {
        "items": [
            {
                "id": 1,
                "clave": "pelicula",
                "pareja_id": null,
                "tipo": "recompensa",
                "nombre": "Elegir película",
                "descripcion": "Elige la película de la próxima noche",
                "coste": 15,
                "activo": true,
                "creado_por_id": null,
                "created_at": "2026-11-01 10:00:00",
                "creado_por_nombre": null,
                "alcance": "global"
            }
        ],
        "personalizados": 0,
        "maximo_personalizados": 15,
        "tipos": ["recompensa", "permiso", "ganancia"],
        "coste_maximo": 100
    }
}
```

Cada artículo incluye `alcance` (`global` o `pareja`). Los artículos globales
tienen `pareja_id: null` y `clave`; los personalizados tienen `pareja_id` y
`clave: null`.

### POST `/api/tienda_item_create.php`

Crea un artículo personalizado de la pareja.

```json
{
    "tipo": "recompensa",
    "nombre": "Noche de cine",
    "descripcion": "Elegir la película y preparar palomitas",
    "coste": 20
}
```

- `tipo` debe ser `recompensa`, `permiso` o `ganancia`.
- `nombre` es obligatorio, máximo 100 caracteres.
- `descripcion` es opcional, máximo 255 caracteres.
- `coste` debe ser un entero entre 1 y 100.
- Cada pareja puede tener como máximo 15 artículos personalizados activos; al
  superar el límite se devuelve `409`.
- Devuelve `201` con el artículo creado en `data`.

### POST `/api/tienda_item_update.php`

Actualiza un artículo personalizado de la pareja. Acepta `id` y los mismos
campos que el alta. Los artículos globales no se pueden modificar (`404`).

### POST `/api/tienda_item_delete.php`

```json
{
    "id": 12
}
```

Desactiva un artículo personalizado. No se borra físicamente, para conservar el
historial de tareas. Los artículos globales no se pueden desactivar (`404`).

### POST `/api/tarea_create.php`

Compra un artículo creando una tarea pendiente de confirmación por la pareja.

```json
{
    "item_id": 1
}
```

- `item_id` debe existir y estar disponible para la pareja (`404` si no).
- El destinatario de la tarea es siempre la pareja del solicitante.
- En `recompensa` y `permiso`, se descuenta el coste al solicitante y se crea un
  evento `resta` de origen `tienda`. Si no hay saldo suficiente: `409`.
- En `ganancia`, no se descuenta nada y la tarea se crea sin evento.
- Devuelve `201` con la tarea creada.

Cada tarea incluye `id`, `pareja_id`, `item_id`, `tipo`, `titulo`, `coste`,
`estado`, `solicitante_id`, `destinatario_id`, `resuelto_por_id`, `evento_id`,
`created_at`, `resuelto_en`, `item_nombre`, `item_descripcion`,
`solicitante_nombre`, `destinatario_nombre` y `resuelto_por_nombre`.

Estados posibles: `pendiente`, `cumplida`, `rechazada` y `cancelada`.

### GET `/api/tareas.php`

Requiere sesión y pareja. Devuelve las tareas de la pareja, de la más reciente a
la más antigua. Parámetro opcional `estado` (`pendiente`, `cumplida`,
`rechazada` o `cancelada`).

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "pareja_id": 1,
            "item_id": 1,
            "tipo": "recompensa",
            "titulo": "Elegir película",
            "coste": 15,
            "estado": "pendiente",
            "solicitante_id": 1,
            "destinatario_id": 2,
            "resuelto_por_id": null,
            "evento_id": 5,
            "created_at": "2026-11-25 09:00:00",
            "resuelto_en": null,
            "item_nombre": "Elegir película",
            "item_descripcion": "Elige la película de la próxima noche",
            "solicitante_nombre": "Daria",
            "destinatario_nombre": "Alex",
            "resuelto_por_nombre": null
        }
    ],
    "meta": {
        "total": 1,
        "estado": null
    }
}
```

### POST `/api/tarea_confirmar.php`

```json
{
    "id": 1
}
```

Solo la pareja destinataria puede confirmar (`403` en caso contrario). La tarea
pasa a `cumplida`. Si es de tipo `ganancia` y aún no tenía evento, se crea un
evento `suma` de origen `tarea` que acredita las coins al solicitante. Si la
tarea ya está resuelta: `409`.

### POST `/api/tarea_rechazar.php`

```json
{
    "id": 1
}
```

Solo la pareja destinataria puede rechazar (`403`). La tarea pasa a `rechazada`.
Si tenía un cargo asociado, el evento se anula y las coins se devuelven al
solicitante.

### POST `/api/tarea_cancelar.php`

```json
{
    "id": 1
}
```

Solo quien solicitó la tarea puede cancelarla (`403`). La tarea pasa a
`cancelada`. Si tenía un cargo asociado, el evento se anula y las coins se
devuelven.

## 10. Bonos automáticos

Los bonos se procesan al llamar a `check_login.php` con sesión y pareja. Se
entregan automáticamente en la fecha exacta, sin ventana de gracia.

- **Cumpleaños:** `+50 coins` a la persona que cumple años.
- **Aniversario:** `+50 coins` a cada miembro de la pareja.
- El aniversario solo genera bono a partir del año siguiente al de
  `fecha_aniversario`. Vincularse hoy no concede un bono inmediato.
- Los cumpleaños y aniversarios del **29 de febrero** se celebran el **28 de
  febrero** en años no bisiestos.
- Cada bono se entrega una sola vez por `(usuario_id, tipo, año)`. Repetir
  `check_login` el mismo día o el mismo año no duplica nada.
- Cada bono crea un evento `suma` de origen `bono`, por lo que cuenta en el
  saldo y en las estadísticas.
- Debido a la semántica de los eventos `suma` (acreditan a la pareja del
  titular), el evento se guarda con el otro miembro como titular y el
  beneficiario como `registrado_por_id`.

En `check_login`, `data.bonos` contiene solo los bonos creados en esa llamada:

```json
{
    "id": 1,
    "pareja_id": 1,
    "usuario_id": 1,
    "tipo": "cumpleanos",
    "anio": 2026,
    "importe": 50,
    "evento_id": 10,
    "nombre": "Daria"
}
```

`tipo` puede ser `cumpleanos` o `aniversario`.

## 11. Predefinidos

Cada usuario gestiona únicamente sus propios predefinidos. El nombre es único
por usuario y no distingue mayúsculas de minúsculas.

### GET `/api/predefinidos_list.php`

Devuelve los predefinidos del usuario autenticado.

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "usuario_id": 1,
            "nombre": "Cena",
            "coste": 20,
            "efecto": "resta"
        }
    ],
    "meta": {
        "total": 1
    }
}
```

### GET `/api/predefinido_get.php?id=`

Devuelve un predefinido propio. Si no existe o es de otro usuario: `404`.

### POST `/api/predefinido_create.php`

**Entrada:**

```json
{
    "nombre": "Cena",
    "coste": 20,
    "efecto": "resta"
}
```

**Validaciones:**

- Nombre obligatorio, máximo 100 caracteres.
- Coste entero mayor que cero.
- Efecto `resta` o `suma`.
- No puede repetirse el nombre entre los predefinidos del mismo usuario (`409`).

**Respuesta correcta (201):** incluye `id`, `usuario_id`, `nombre`, `coste` y
`efecto` en `data`.

### POST `/api/predefinido_update.php`

**Entrada:**

```json
{
    "id": 1,
    "nombre": "Cena especial",
    "coste": 25,
    "efecto": "resta"
}
```

Comprueba la propiedad del predefinido y que el nombre no choque con otro.

### POST `/api/predefinido_delete.php`

**Entrada:**

```json
{
    "id": 1
}
```

Antes de borrar el predefinido se pone a `NULL` la referencia en los eventos que
lo usaban, para conservar el historial y no romper la clave foránea.

## 12. Eventos

### GET `/api/eventos_list.php`

Devuelve los eventos del usuario y de su pareja. Admite estos parámetros GET:

| Parámetro | Descripción |
|---|---|
| `usuario_id` | Filtrar por persona (solo el usuario o su pareja) |
| `efecto` | `resta` o `suma` |
| `desde` | Fecha inicial `YYYY-MM-DD` |
| `hasta` | Fecha final `YYYY-MM-DD` |
| `nombre` | Búsqueda parcial por nombre |
| `anulado` | `true` o `false`. Si se omite, se devuelven ambos |
| `orden` | `fecha`, `coste`, `persona`, `usuario`, `nombre` o `id` |
| `dir` | `asc` o `desc` |

La ordenación por defecto es `fecha DESC` y, en caso de empate, `id DESC`.

Cada evento incluye además `usuario_nombre` y `registrado_por_nombre`.

El campo `origen` identifica quién generó el movimiento:

| `origen` | Significado |
|---|---|
| `manual` | Evento creado desde el historial por la pareja |
| `tienda` | Compra de una recompensa o un permiso |
| `tarea` | Ganancia acreditada al confirmar una tarea |
| `bono` | Cumpleaños o aniversario |
| `planta` | Cambio de planta |

Los eventos con `origen` distinto de `manual` son movimientos del sistema y no se
pueden modificar ni anular desde los endpoints del historial.

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "usuario_id": 2,
            "registrado_por_id": 1,
            "predefinido_id": null,
            "nombre": "Cena",
            "fecha": "2026-11-06",
            "coste": 20,
            "efecto": "resta",
            "origen": "manual",
            "anulado": false,
            "created_at": "2026-11-06 20:15:00",
            "usuario_nombre": "Alex",
            "registrado_por_nombre": "Daria"
        }
    ],
    "meta": {
        "total": 1,
        "filtros": {}
    }
}
```

### GET `/api/evento_get.php?id=`

Devuelve un evento de la pareja, incluido si está anulado. Si no pertenece a la
pareja: `404`.

### POST `/api/evento_create.php`

**Entrada:**

```json
{
    "usuario_id": 2,
    "predefinido_id": 1,
    "nombre": "Cena",
    "fecha": "2026-11-06",
    "coste": 20,
    "efecto": "resta"
}
```

**Validaciones:**

- `usuario_id` debe ser el usuario autenticado o su pareja.
- Nombre obligatorio, máximo 100 caracteres.
- Fecha real en formato `YYYY-MM-DD`.
- Coste entero mayor que cero.
- Efecto `resta` o `suma`.
- Si se envía `predefinido_id`, debe existir y pertenecer al usuario autenticado.
- `registrado_por_id` se toma siempre de la sesión.
- El evento se crea con `origen: manual`. Los movimientos del sistema solo los
  generan la tienda, las tareas, los bonos y la planta.

### POST `/api/evento_update.php`

**Entrada:** igual que en el alta, añadiendo `id`.

Los campos que no se envían conservan su valor actual. No se puede modificar un
evento anulado (`409`) ni un evento del sistema (`origen` distinto de `manual`),
que también devuelve `409`. Se comprueba que el evento y el usuario destinatario
pertenezcan a la pareja.

### POST `/api/evento_anular.php`

**Entrada:**

```json
{
    "id": 1
}
```

Cambia `anulado` a `TRUE` sin borrar el registro. Si ya estaba anulado: `409`.
Los eventos del sistema (`tienda`, `tarea`, `bono` o `planta`) no se pueden
anular desde este endpoint: `409`.

## 13. Recargas

### GET `/api/recargas_list.php`

Devuelve las recargas del usuario y de su pareja, ordenadas por fecha
descendente. Cada recarga incluye `usuario_nombre`.

### POST `/api/recarga_create.php`

**Entrada:**

```json
{
    "usuario_id": 2,
    "importe": 50,
    "fecha": "2026-11-06",
    "motivo": "Recompensa"
}
```

**Validaciones:**

- `usuario_id` debe ser el usuario autenticado o su pareja.
- Importe entero mayor que cero.
- Fecha real en formato `YYYY-MM-DD`.
- Motivo opcional, máximo 255 caracteres.

### POST `/api/recarga_update.php`

**Entrada:**

```json
{
    "id": 1,
    "importe": 60,
    "fecha": "2026-11-07",
    "motivo": "Recompensa especial"
}
```

Modifica el importe, la fecha y el motivo. El usuario destinatario no cambia.
Solo se puede modificar una recarga de la pareja.

### POST `/api/recarga_delete.php`

**Entrada:**

```json
{
    "id": 1
}
```

Solo se puede borrar una recarga de la pareja; si no, `404`.

## 14. Saldos

### GET `/api/saldos.php`

Devuelve el saldo del usuario autenticado y de su pareja. Requiere sesión y
pareja vinculada.

Parámetro opcional: `usuario_id`, que debe ser el propio usuario o su pareja
(`403` en caso contrario).

```json
{
    "success": true,
    "data": {
        "usuarios": [
            {
                "usuario_id": 1,
                "nombre": "Daria",
                "saldo_inicial": 100,
                "total_recargas": 50,
                "total_resta": 20,
                "total_suma": 10,
                "saldo": 140
            },
            {
                "usuario_id": 2,
                "nombre": "Alex",
                "saldo_inicial": 0,
                "total_recargas": 0,
                "total_resta": 0,
                "total_suma": 5,
                "saldo": 5
            }
        ],
        "saldo_conjunto": 145
    }
}
```

Fórmula: `saldo = saldo_inicial + total_recargas + total_suma - total_resta`.
Los eventos anulados no se contabilizan. La regla se calcula en
`Event::calcularImpacto()` para no duplicarla:

- Un evento `resta` descuenta el coste al usuario titular.
- Un evento `suma` acredita el coste a la pareja del titular.

Los eventos generados por la tienda, las tareas, los bonos y la planta siguen
estas mismas reglas. Por eso los movimientos del sistema también modifican el
saldo y aparecen en las estadísticas.

## 15. Estadísticas

### GET `/api/stats.php`

Estadísticas de la pareja agrupadas por periodo. Requiere sesión y pareja.

| Parámetro | Descripción |
|---|---|
| `granularidad` | `mes` (por defecto), `trimestre` o `año` (también `ano`) |
| `desde` | Fecha inicial `YYYY-MM-DD` |
| `hasta` | Fecha final `YYYY-MM-DD` |
| `usuario_id` | Limitar a un miembro de la pareja |

Si se omite `desde`, se usan los últimos 12 meses, 8 trimestres o 5 años según
la granularidad. El rango máximo es de 120 periodos. Los periodos sin datos se
devuelven a cero.

```json
{
    "success": true,
    "data": [
        {
            "periodo": "2026-11",
            "etiqueta": "nov 2026",
            "inicio": "2026-11-01",
            "fin": "2026-11-30",
            "eventos": 3,
            "total_resta": 20,
            "total_suma": 15,
            "neto": -5,
            "recargas": 50,
            "variacion": 45
        }
    ],
    "meta": {
        "granularidad": "mes",
        "desde": "2026-01-01",
        "hasta": "2026-12-31",
        "total_periodos": 12,
        "total_eventos": 3,
        "total_resta": 20,
        "total_suma": 15,
        "total_recargas": 50,
        "usuario_id": null
    }
}
```

`neto = total_suma - total_resta` y `variacion = recargas + neto`.

## 16. Administración

Todas las rutas de esta sección requieren rol `superadmin`; en caso contrario se
devuelve `403`. Los listados usan la respuesta paginada estándar:

```json
{
    "success": true,
    "data": [],
    "total": 0,
    "pagina": 1,
    "limite": 50,
    "paginas": 0
}
```

### GET `/api/admin_usuarios_list.php`

Filtros opcionales: `q` (nombre, email o código), `rol`, `activo`,
`con_pareja`, `pagina` y `limite`. Cada usuario incluye `fecha_nacimiento`,
`pareja_id`, `pareja_usuario_id` y `pareja_nombre` cuando tiene pareja.

### GET `/api/admin_usuario_get.php?id=`

Devuelve `data.usuario` y `data.pareja` (o `null`).

### POST `/api/admin_usuario_create.php`

```json
{
    "nombre": "Nuevo",
    "email": "nuevo@example.com",
    "password": "Password123",
    "rol": "usuario",
    "saldo_inicial": 25,
    "fecha_nacimiento": "1995-05-20",
    "activo": true
}
```

`rol` solo admite `usuario` o `superadmin`. `saldo_inicial` es opcional y debe
ser un entero mayor o igual que cero. `fecha_nacimiento` es opcional y debe ser
una fecha real `YYYY-MM-DD`. Devuelve `201` y el usuario creado. Email
duplicado: `409`.

### POST `/api/admin_usuario_update.php`

Acepta `id` y los mismos campos que el alta. Los campos omitidos conservan su
valor actual. `password` y `fecha_nacimiento` son opcionales; para borrar la
fecha de nacimiento se envía `null` o una cadena vacía. Reglas de protección:

- Un administrador no puede desactivarse ni quitarse el rol a sí mismo (`409`).
- No se puede dejar la aplicación sin ningún `superadmin` activo (`409`).
- El email debe seguir siendo único (`409`).

### POST `/api/admin_usuario_baja.php`

```json
{
    "id": 5
}
```

Baja lógica (`activo = FALSE`). No permite darse de baja a uno mismo ni dejar
sin superadministradores activos. Si el usuario ya estaba inactivo: `409`.

### GET `/api/admin_parejas_list.php`

Filtro opcional `q` (nombre, email o código de cualquiera de los dos miembros) y
paginación. Cada pareja incluye `fecha_aniversario`, `usuario_1` y `usuario_2`.

### GET `/api/admin_pareja_get.php?id=`

Devuelve una pareja concreta, incluida su `fecha_aniversario`.

### POST `/api/admin_pareja_set.php`

```json
{
    "usuario_1_id": 2,
    "usuario_2_id": 5
}
```

Crea o corrige una pareja. Si alguno de los usuarios ya tenía pareja, se elimina
esa relación previa dentro de la misma transacción. Devuelve `201` si la crea y
`200` si ya existía. Ambos usuarios deben existir y estar activos. Al crear una
pareja nueva, `fecha_aniversario` se inicializa con la fecha actual.

### POST `/api/admin_pareja_delete.php`

```json
{
    "id": 3
}
```

Elimina la pareja, pero no los usuarios ni sus eventos o recargas.

### GET `/api/admin_resumen.php`

Resumen global para el panel:

```json
{
    "success": true,
    "data": {
        "usuarios": {
            "total": 10,
            "activos": 9,
            "superadmins_activos": 1
        },
        "parejas": { "total": 4 },
        "eventos": {
            "total": 30,
            "activos": 28,
            "anulados": 2,
            "total_resta": 300,
            "total_suma": 120
        },
        "recargas": {
            "total": 6,
            "importe_total": 400
        },
        "saldo_global": 220
    }
}
```

### GET `/api/admin_eventos_list.php`

Filtros: `usuario_id`, `pareja_id`, `q` (nombre), `efecto`, `desde`, `hasta`,
`anulado`, `pagina` y `limite`. Incluye `usuario_nombre`,
`registrado_por_nombre` y `origen`.

### GET `/api/admin_recargas_list.php`

Filtros: `usuario_id`, `pareja_id`, `q` (motivo), `desde`, `hasta`, `pagina` y
`limite`. Incluye `usuario_nombre`.

### GET `/api/admin_stats.php`

Estadísticas globales con la misma granularidad y formato que `/api/stats.php`.
Acepta además `usuario_id` y `pareja_id`. Si la pareja indicada no existe:
`404`.

## 17. Cuenta y salud

### POST `/api/password_change.php`

Requiere sesión, pero no pareja.

```json
{
    "password_actual": "Password123",
    "password_nueva": "Password456"
}
```

La contraseña actual se verifica con `password_verify()`; si no coincide: `401`.
La nueva debe tener al menos 4 caracteres, una mayúscula y como máximo 72 bytes
(límite de bcrypt). Tras el cambio se regenera el ID de sesión.

### GET `/api/health.php`

Comprobación de disponibilidad. No requiere sesión. Devuelve `200` si la base de
datos responde o `503` si no:

```json
{
    "success": true,
    "data": {
        "estado": "ok",
        "base_de_datos": "ok",
        "hora": "2026-11-16T17:15:19+01:00"
    }
}
```

## 18. Recuerdos y logros

### GET `/api/recuerdos_list.php`

Requiere sesión y pareja. Lista los recuerdos de la pareja, del más reciente al
más antiguo por `fecha` y luego por `id`. Cada recuerdo incluye el nombre de
quien lo creó.

```json
{
    "success": true,
    "data": [
        {
            "id": 2,
            "pareja_id": 1,
            "creado_por_id": 2,
            "titulo": "Aniversario",
            "fecha": "2026-06-01",
            "nota": "Cena especial.",
            "created_at": "2026-06-01 21:00:00",
            "creado_por_nombre": "Alex"
        }
    ],
    "meta": { "total": 1 }
}
```

### POST `/api/recuerdo_create.php`

Requiere sesión y pareja.

```json
{
    "titulo": "Primer viaje",
    "fecha": "2026-05-01",
    "nota": "Fuimos a la playa."
}
```

- `titulo`: obligatorio, máximo 100 caracteres.
- `fecha`: obligatoria y debe ser una fecha real `YYYY-MM-DD`.
- `nota`: obligatoria, máximo 255 caracteres.
- Devuelve `201` con el recuerdo creado en `data`.
- Errores de validación: `400`. Sin sesión: `401`. Sin pareja: `403`.

### GET `/api/logros.php`

Requiere sesión y pareja. Calcula los logros al vuelo con los datos reales, sin
guardar una tabla de logros. Cada logro incluye `id`, `titulo`, `detalle`,
`emoji`, `desbloqueado`, `progreso` y `objetivo`.

| `id` | Logro | Cómo se desbloquea |
|---|---|---|
| `first-care` | Primer cuidado | Al menos un check-in de la pareja |
| `week` | Una semana creciendo | `racha_maxima >= 7` |
| `together` | En equipo | Al menos un día completado por los dos |
| `month` | Un mes de constancia | `racha_maxima >= 30` |
| `collector` | Pequeños coleccionistas | 3 artículos distintos comprados en la tienda |

Para `week` y `month` se usa `racha_maxima`, no la racha actual, para que un
logro no vuelva a bloquearse si la racha baja. En `collector` cuentan las tareas
en estado `pendiente` o `cumplida`; las rechazadas y canceladas no suman.

```json
{
    "success": true,
    "data": [
        {
            "id": "first-care",
            "titulo": "Primer cuidado",
            "detalle": "Cuidar una planta por primera vez",
            "emoji": "🌱",
            "desbloqueado": true,
            "progreso": 1,
            "objetivo": 1
        }
    ],
    "meta": { "total": 5, "desbloqueados": 1 }
}
```

## 19. Seguridad y control de acceso

- Contraseñas con `password_hash()` y `password_verify()`, con un máximo de 72
  bytes para bcrypt.
- Sesiones PHP con cookie `HttpOnly`, `SameSite=Lax` y `Secure` automático bajo
  HTTPS.
- Regeneración del ID de sesión al iniciar sesión y al cambiar la contraseña.
- Comprobación de que la cuenta sigue activa en cada petición privada.
- Consultas con sentencias preparadas de PDO y listas blancas para ordenación.
- Validación de fechas reales, enteros positivos, emails, códigos y longitudes.
- Control de propiedad de predefinidos, eventos, recargas y saldos.
- Los recuerdos se filtran siempre por `pareja_id`: un usuario no puede ver los
  de otra pareja. Los logros también se calculan solo con los datos de la pareja
  autenticada.
- Los eventos del sistema (`tienda`, `tarea`, `bono` y `planta`) están
  protegidos: no se pueden modificar ni anular desde el historial manual.
- La tienda limita los artículos personalizados a 15 por pareja y el coste a
  100 coins. Las compras y las tareas se resuelven en transacciones con bloqueo.
- Los bonos usan una clave única `(usuario_id, tipo, año)` para no entregarse dos
  veces.
- Las fechas de nacimiento y aniversario se validan como fechas reales antes de
  guardarse.
- Los endpoints de escritura solo aceptan `POST`.
- En peticiones `POST`, si el navegador envía `Origin`, debe coincidir con el
  host y puerto del servidor. Es una defensa adicional contra CSRF.
- Los endpoints de administración exigen `superadmin` y protegen la cuenta
  propia y el último superadministrador activo.
- `Cache-Control: no-store` y `X-Content-Type-Options: nosniff` en todas las
  respuestas.
- Los errores de base de datos se registran en el log y no se muestran al
  cliente.

## 20. Resumen de endpoints implementados

| Funcionalidad | Método | Endpoint |
|---|---|---|
| Salud del servicio | GET | `health.php` |
| Registro | POST | `register.php` |
| Login | POST | `login.php` |
| Logout | POST | `logout.php` |
| Comprobar sesión | GET | `check_login.php` |
| Cambiar contraseña | POST | `password_change.php` |
| Mi código | GET | `mi_codigo.php` |
| Vincular pareja | POST | `pareja_vincular.php` |
| Estado de pareja | GET | `pareja_estado.php` |
| Estado de la racha | GET | `streak.php` |
| Registrar entrada de la racha | POST | `streak_checkin.php` |
| Perfil y fechas | GET | `perfil.php` |
| Actualizar cumpleaños | POST | `perfil_fecha_nacimiento.php` |
| Actualizar aniversario | POST | `pareja_aniversario.php` |
| Estado de la planta | GET | `planta.php` |
| Elegir o cambiar planta | POST | `planta_elegir.php` |
| Listar recuerdos | GET | `recuerdos_list.php` |
| Crear recuerdo | POST | `recuerdo_create.php` |
| Consultar logros | GET | `logros.php` |
| Ver la tienda | GET | `tienda.php` |
| Crear artículo propio | POST | `tienda_item_create.php` |
| Modificar artículo propio | POST | `tienda_item_update.php` |
| Desactivar artículo propio | POST | `tienda_item_delete.php` |
| Listar tareas | GET | `tareas.php` |
| Crear tarea o compra | POST | `tarea_create.php` |
| Confirmar tarea | POST | `tarea_confirmar.php` |
| Rechazar tarea | POST | `tarea_rechazar.php` |
| Cancelar tarea | POST | `tarea_cancelar.php` |
| Listar predefinidos | GET | `predefinidos_list.php` |
| Obtener predefinido | GET | `predefinido_get.php` |
| Crear predefinido | POST | `predefinido_create.php` |
| Modificar predefinido | POST | `predefinido_update.php` |
| Eliminar predefinido | POST | `predefinido_delete.php` |
| Listar eventos | GET | `eventos_list.php` |
| Obtener evento | GET | `evento_get.php` |
| Crear evento | POST | `evento_create.php` |
| Modificar evento | POST | `evento_update.php` |
| Anular evento | POST | `evento_anular.php` |
| Listar recargas | GET | `recargas_list.php` |
| Crear recarga | POST | `recarga_create.php` |
| Modificar recarga | POST | `recarga_update.php` |
| Eliminar recarga | POST | `recarga_delete.php` |
| Consultar saldos | GET | `saldos.php` |
| Estadísticas de pareja | GET | `stats.php` |
| Listar usuarios (admin) | GET | `admin_usuarios_list.php` |
| Obtener usuario (admin) | GET | `admin_usuario_get.php` |
| Crear usuario (admin) | POST | `admin_usuario_create.php` |
| Modificar usuario (admin) | POST | `admin_usuario_update.php` |
| Baja lógica (admin) | POST | `admin_usuario_baja.php` |
| Listar parejas (admin) | GET | `admin_parejas_list.php` |
| Obtener pareja (admin) | GET | `admin_pareja_get.php` |
| Crear o corregir pareja (admin) | POST | `admin_pareja_set.php` |
| Eliminar pareja (admin) | POST | `admin_pareja_delete.php` |
| Resumen global (admin) | GET | `admin_resumen.php` |
| Listar eventos (admin) | GET | `admin_eventos_list.php` |
| Listar recargas (admin) | GET | `admin_recargas_list.php` |
| Estadísticas globales (admin) | GET | `admin_stats.php` |

## 21. Notas sobre `db/schema.sql`

`db/schema.sql` es el esquema canónico para MySQL/MariaDB (probado con MariaDB
10.4 de XAMPP) y ya incluye todas las tablas de esta fase. Todas las tablas usan
el motor `InnoDB` y el juego de caracteres `utf8mb4` con collation
`utf8mb4_unicode_ci`. Los identificadores son `INT AUTO_INCREMENT` y las marcas
de tiempo son `DATETIME DEFAULT CURRENT_TIMESTAMP`.

- **Racha:** `pareja_streaks` (contador por pareja) y `pareja_streak_checkins`
  (una entrada por usuario y día), con el índice `idx_streak_checkins_fecha`.
- **Plantas:** `pareja_plantas` (una fila por pareja y usuario, con clave
  primaria `(pareja_id, usuario_id)`, especie, etapa, progreso y marcas de
  degradación/reinicio) y `pareja_planta_cambios` (histórico de cambios con su
  evento asociado).
- **Recuerdos:** `pareja_recuerdos` (título, fecha, nota y autor) con el índice
  `idx_pareja_recuerdos_fecha` sobre `(pareja_id, fecha)`.
- **Tienda:** `tienda_items`. Los artículos globales tienen `clave` y
  `pareja_id NULL`; los personalizados tienen `pareja_id`. Incluye
  `idx_tienda_items_pareja`. El esquema inserta los 11 artículos globales con
  `ON DUPLICATE KEY UPDATE`, de modo que el script se puede reimportar sin
  duplicarlos.
- **Tareas:** `pareja_tareas`, con estado, solicitante, destinatario, evento
  asociado e `idx_pareja_tareas_pareja`.
- **Bonos:** `pareja_bonos`, con la restricción única
  `(usuario_id, tipo, anio)` que garantiza la idempotencia.
- **Fechas especiales:** `usuarios.fecha_nacimiento` y
  `parejas.fecha_aniversario`.
- **Eventos:** `eventos.origen` con valor por defecto `manual` y
  `CHECK (origen IN ('manual', 'tienda', 'tarea', 'bono', 'planta'))`.

El resto del esquema funciona con el código, pero se recomienda al SM aplicar
estos cambios cuando pueda modificarse el archivo `.sql`:

- `eventos.predefinido_id ... ON DELETE SET NULL` para no tener que desvincular
  los eventos manualmente desde PHP.
- `CHECK (usuario_1_id < usuario_2_id)` en `parejas` para garantizar el orden
  canónico de la pareja también a nivel de base de datos.
- Valor por defecto `'usuario'` en `usuarios.rol`.
- Índices en `eventos(usuario_id, fecha)`, `recargas(usuario_id, fecha)` y
  `eventos(fecha)` para acelerar los listados y las estadísticas.
- `CHECK (coste > 0)` en `eventos` y `predefinidos`, y `CHECK (importe > 0)` en
  `recargas`.
- `CHECK (saldo_inicial >= 0)` en `usuarios` si se quiere impedir un saldo
  inicial negativo.

El código actual ya normaliza el orden de la pareja, valida los importes en PHP
y desvincula los eventos antes de borrar un predefinido, por lo que estos
cambios son una mejora de integridad, no un requisito para que la aplicación
funcione.

