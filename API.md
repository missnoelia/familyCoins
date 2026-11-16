# FamilyCoinsApp – Documentación de la API

## 1. Introducción

FamilyCoinsApp es una aplicación para que una pareja controle sus FamilyCoins.
Cada evento pertenece a una persona y tiene un efecto:

- **resta**: el coste se descuenta del saldo de esa persona.
- **suma**: el coste se suma al saldo de su pareja.

El Backend está desarrollado en **PHP 8** y utiliza **PostgreSQL**. La
comunicación con el Frontend se hace mediante una API REST que devuelve siempre
JSON.

Alcance implementado en esta fase: **sprints 1 a 4**. Los endpoints de saldos,
estadísticas y administración están planificados para el sprint 5 y se indican
al final como pendientes.

## 2. Configuración general

- **Base URL:** `/api/`
- **Formato de respuesta:** JSON (`Content-Type: application/json`).
- **Métodos:** `GET` para consultas y `POST` para altas, modificaciones y bajas.
- **Autenticación:** sesiones PHP.
- **Base de datos:** PostgreSQL, mediante PDO.
- **Zona horaria:** las fechas se envían y se devuelven como `YYYY-MM-DD`.

Los endpoints privados necesitan una sesión activa. Salvo las rutas de
autenticación y vinculación de pareja, también exigen que el usuario tenga una
pareja vinculada.

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
    "password": "Password123"
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
        "codigo": "ABC1234"
    }
}
```

**Validaciones:**

- Nombre obligatorio, máximo 100 caracteres.
- Email válido, máximo 150 caracteres y no registrado previamente.
- Contraseña de al menos 4 caracteres y con una mayúscula.
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
            "rol": "usuario"
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

Indica si hay sesión activa y si el usuario tiene pareja.

**Respuesta sin sesión (200):**

```json
{
    "success": true,
    "data": {
        "logged": false,
        "usuario": null,
        "tiene_pareja": false,
        "pareja": null
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
            "saldo_inicial": null
        },
        "tiene_pareja": true,
        "pareja": {
            "id": 2,
            "nombre": "Alex",
            "email": "alex@example.com",
            "codigo": "XYZ5678",
            "rol": "usuario"
        }
    }
}
```

Si la cuenta fue dada de baja mientras la sesión estaba abierta, se cierra la
sesión y se devuelve `logged: false`.

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
            "rol": "usuario"
        }
    }
}
```

Si no tiene pareja, `pareja` es `null`.

## 6. Predefinidos

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

## 7. Eventos

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

### POST `/api/evento_update.php`

**Entrada:** igual que en el alta, añadiendo `id`.

Los campos que no se envían conservan su valor actual. No se puede modificar un
evento anulado (`409`). Se comprueba que el evento y el usuario destinatario
pertenezcan a la pareja.

### POST `/api/evento_anular.php`

**Entrada:**

```json
{
    "id": 1
}
```

Cambia `anulado` a `TRUE` sin borrar el registro. Si ya estaba anulado: `409`.

## 8. Recargas

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

## 9. Cálculo del saldo (preparado en el modelo)

La lógica del efecto está centralizada en `Event::calcularImpacto()`:

- Un evento `resta` descuenta el coste al usuario titular.
- Un evento `suma` acredita el coste a la pareja del titular.
- Los eventos anulados no cuentan.

El endpoint de saldos se implementará en el sprint 5 usando este método, para no
duplicar la regla de negocio en varios sitios.

## 10. Seguridad y control de acceso

- Contraseñas con `password_hash()` y `password_verify()`.
- Sesiones PHP con cookie `HttpOnly` y `SameSite=Lax`.
- Regeneración del ID de sesión al iniciar sesión.
- Comprobación de que la cuenta sigue activa en cada petición privada.
- Consultas con sentencias preparadas de PDO.
- Validación de fechas reales, enteros positivos, códigos y longitudes.
- Control de propiedad de predefinidos, eventos y recargas.
- Los endpoints de escritura solo aceptan `POST`.
- Los errores de base de datos se registran en el log y no se muestran al
  cliente.

## 11. Pendiente para el sprint 5

Estos endpoints están planificados, pero **no están implementados todavía**:

| Función | Método | Endpoint previsto |
|---|---|---|
| Consultar saldos | GET | `/api/saldos.php` |
| Estadísticas por periodo | GET | `/api/stats.php?granularidad=mes\|trimestre\|año` |
| Listar usuarios (admin) | GET | `/api/admin_usuarios_list.php` |
| Obtener usuario (admin) | GET | `/api/admin_usuario_get.php?id=` |
| Crear usuario (admin) | POST | `/api/admin_usuario_create.php` |
| Modificar usuario (admin) | POST | `/api/admin_usuario_update.php` |
| Baja lógica (admin) | POST | `/api/admin_usuario_baja.php` |
| Crear o corregir pareja (admin) | POST | `/api/admin_pareja_set.php` |

Los endpoints de administración requerirán rol `superadmin`.

## 12. Resumen de endpoints implementados

| Funcionalidad | Método | Endpoint |
|---|---|---|
| Registro | POST | `register.php` |
| Login | POST | `login.php` |
| Logout | POST | `logout.php` |
| Comprobar sesión | GET | `check_login.php` |
| Mi código | GET | `mi_codigo.php` |
| Vincular pareja | POST | `pareja_vincular.php` |
| Estado de pareja | GET | `pareja_estado.php` |
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

## 13. Notas sobre `db/schema.sql`

El esquema actual funciona con el código, pero se recomienda al SM aplicar estos
cambios cuando pueda modificarse el archivo `.sql`:

- `eventos.predefinido_id ... ON DELETE SET NULL` para no tener que desvincular
  los eventos manualmente desde PHP.
- `CHECK (usuario_1_id < usuario_2_id)` en `parejas` para garantizar el orden
  canónico de la pareja también a nivel de base de datos.
- Valor por defecto `'usuario'` en `usuarios.rol`.
- Índices en `eventos(usuario_id, fecha)` y `recargas(usuario_id, fecha)` para
  acelerar los listados y las futuras estadísticas.
- `CHECK (coste > 0)` en `eventos` y `predefinidos`, y `CHECK (importe > 0)` en
  `recargas`.

El código actual ya normaliza el orden de la pareja y desvincula los eventos
antes de borrar un predefinido, por lo que estos cambios son una mejora de
integridad, no un requisito para que la aplicación funcione.
