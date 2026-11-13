# FamilyCoinsApp – Documentación de la API

## 1. Introducción

En FamilyCoinsApp me encargo del desarrollo Backend. Esta parte gestiona las peticiones que recibe del Frontend, realiza las comprobaciones necesarias y se comunica con la base de datos.

El Backend está desarrollado en **PHP** y utiliza **PostgreSQL** como sistema gestor de bases de datos. La comunicación entre Frontend y Backend se realiza mediante una API REST y las respuestas se devuelven en formato JSON.

En este documento se describen los endpoints disponibles, los datos que reciben, las respuestas que devuelven y las validaciones que se deben realizar.

## 2. Configuración general

- **Base URL:** `/api/`
- **Formato de respuesta:** JSON.
- **Métodos HTTP:** GET y POST.
- **Autenticación:** sesiones PHP.
- **Base de datos:** PostgreSQL.
- **Conexión a la base de datos:** PDO.

Los endpoints privados necesitan una sesión activa para identificar al usuario que realiza la petición.

Los endpoints de administración requieren además que el usuario tenga el rol `superadmin`.

## 3. Formato de respuestas

Todas las respuestas siguen una estructura común para que el Frontend pueda interpretar fácilmente el resultado de cada petición.

### Respuesta correcta

```json
{
    "success": true,
    "message": "Operación realizada correctamente",
    "data": {}
}
```

### Respuesta de error

```json
{
    "success": false,
    "message": "Descripción del error"
}
```

### Códigos HTTP

| Código | Significado |
|---|---|
| 200 | Operación correcta |
| 201 | Recurso creado |
| 400 | Datos incorrectos |
| 401 | Usuario no autenticado |
| 403 | Acceso denegado |
| 404 | Recurso no encontrado |
| 409 | Conflicto o recurso duplicado |
| 500 | Error interno del servidor |

## 4. Autenticación

### POST `/api/register.php`

Registra un usuario nuevo y genera automáticamente un código único para vincularlo con otra cuenta.

**Parámetros de entrada:**

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
    "message": "Usuario registrado correctamente",
    "data": {
        "id": 1,
        "nombre": "Daria",
        "email": "daria@example.com",
        "codigo": "ABC1234"
    }
}
```

**Validaciones:**

- El email debe tener un formato válido.
- El email no puede estar registrado anteriormente.
- La contraseña debe contener al menos una mayúscula y cuatro caracteres.
- Se genera un código único formado por tres letras y cuatro números.

### POST `/api/login.php`

Comprueba los datos del usuario e inicia su sesión si las credenciales son correctas.

**Parámetros de entrada:**

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
    "message": "Inicio de sesión correcto",
    "data": {
        "id": 1,
        "nombre": "Daria",
        "email": "daria@example.com"
    }
}
```

La contraseña se comprueba mediante `password_verify()`.

### POST `/api/logout.php`

Cierra la sesión del usuario autenticado.

**Respuesta correcta:**

```json
{
    "success": true,
    "message": "Sesión cerrada correctamente"
}
```

### GET `/api/check_login.php`

Comprueba si existe una sesión activa y si el usuario tiene una pareja vinculada.

**Respuesta correcta:**

```json
{
    "success": true,
    "data": {
        "logged": true,
        "usuario": {
            "id": 1,
            "nombre": "Daria"
        },
        "tiene_pareja": true
    }
}
```

## 5. Parejas

Cada usuario dispone de un código que permite vincular su cuenta con la de otra persona.

### GET `/api/mi_codigo.php`

Devuelve el código de vinculación del usuario autenticado.

**Respuesta correcta:**

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

**Parámetros de entrada:**

```json
{
    "codigo": "XYZ5678"
}
```

**Validaciones:**

- El código debe tener tres letras y cuatro números.
- El código debe existir.
- Ninguno de los dos usuarios puede tener ya una pareja.
- Un usuario no puede vincularse consigo mismo.

**Respuesta correcta:**

```json
{
    "success": true,
    "message": "Pareja vinculada correctamente"
}
```

### GET `/api/pareja_estado.php`

Devuelve los datos de la pareja del usuario autenticado.

**Respuesta si tiene pareja:**

```json
{
    "success": true,
    "data": {
        "pareja": {
            "id": 2,
            "nombre": "Alex",
            "codigo": "XYZ5678"
        }
    }
}
```

**Respuesta si no tiene pareja:**

```json
{
    "success": true,
    "data": {
        "pareja": null
    }
}
```

## 6. Predefinidos

Los predefinidos permiten guardar movimientos habituales para poder reutilizarlos sin tener que introducir todos los datos cada vez.

Cada usuario puede gestionar sus propios predefinidos.

### GET `/api/predefinidos_list.php`

Devuelve todos los predefinidos del usuario autenticado.

### GET `/api/predefinido_get.php?id=`

Devuelve un predefinido concreto mediante su ID.

**Parámetro GET:** `id`.

### POST `/api/predefinido_create.php`

Crea un nuevo predefinido.

**Parámetros de entrada:**

```json
{
    "nombre": "Cena",
    "coste": 20,
    "efecto": "resta"
}
```

### POST `/api/predefinido_update.php`

Modifica un predefinido existente.

**Parámetros de entrada:**

```json
{
    "id": 1,
    "nombre": "Cena especial",
    "coste": 25,
    "efecto": "resta"
}
```

### POST `/api/predefinido_delete.php`

Elimina un predefinido mediante su ID.

**Parámetros de entrada:**

```json
{
    "id": 1
}
```

**Validaciones:**

- El nombre es obligatorio.
- El coste debe ser mayor que cero.
- El efecto debe ser `resta` o `suma`.
- El predefinido debe pertenecer al usuario autenticado.
- No puede existir otro predefinido con el mismo nombre para ese usuario.

## 7. Eventos

Los eventos representan los movimientos que se registran en las cuentas de los integrantes de una pareja. Pueden aumentar o reducir el saldo.

### GET `/api/eventos_list.php`

Devuelve los eventos de la pareja. Permite aplicar los siguientes filtros mediante parámetros GET:

| Parámetro | Descripción |
|---|---|
| `usuario_id` | Filtrar por persona |
| `efecto` | Filtrar por `resta` o `suma` |
| `desde` | Fecha inicial |
| `hasta` | Fecha final |
| `nombre` | Buscar por nombre |
| `anulado` | Filtrar eventos anulados (`true` o `false`) |

### GET `/api/evento_get.php?id=`

Devuelve un evento concreto mediante su ID.

### POST `/api/evento_create.php`

Registra un nuevo evento para uno de los integrantes de la pareja.

**Parámetros de entrada:**

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

El usuario que registra el evento se obtiene directamente de la sesión autenticada y se guarda en `registrado_por_id`.

**Validaciones:**

- El nombre es obligatorio.
- El coste debe ser mayor que cero.
- La fecha debe ser válida.
- El usuario destinatario debe pertenecer a la pareja.
- El efecto debe ser `resta` o `suma`.
- Si se utiliza un predefinido, debe pertenecer al usuario que lo creó.

### POST `/api/evento_update.php`

Modifica un evento existente.

**Parámetros de entrada:**

```json
{
    "id": 1,
    "usuario_id": 2,
    "nombre": "Cena especial",
    "fecha": "2026-11-06",
    "coste": 25,
    "efecto": "resta"
}
```

### POST `/api/evento_anular.php`

Anula un evento sin eliminarlo de la base de datos.

**Parámetros de entrada:**

```json
{
    "id": 1
}
```

La anulación cambia el campo `anulado` a `TRUE`. De esta manera se conserva el registro, pero el evento deja de contar para los cálculos de saldos y estadísticas.

## 8. Recargas

Las recargas permiten añadir saldo a uno de los integrantes de la pareja.

### GET `/api/recargas_list.php`

Devuelve las recargas de la pareja.

### POST `/api/recarga_create.php`

Crea una recarga para uno de los usuarios de la pareja.

**Parámetros de entrada:**

```json
{
    "usuario_id": 2,
    "importe": 50,
    "fecha": "2026-11-06",
    "motivo": "Recompensa"
}
```

### POST `/api/recarga_delete.php`

Elimina una recarga mediante su ID.

**Parámetros de entrada:**

```json
{
    "id": 1
}
```

**Validaciones:**

- El importe debe ser mayor que cero.
- La fecha debe ser válida.
- El usuario debe pertenecer a la pareja.

## 9. Saldos y estadísticas

### GET `/api/saldos.php`

Devuelve el saldo actual de cada integrante de la pareja.

**Respuesta de ejemplo:**

```json
{
    "success": true,
    "data": {
        "usuarios": [
            {
                "id": 1,
                "nombre": "Daria",
                "saldo": 150
            },
            {
                "id": 2,
                "nombre": "Alex",
                "saldo": 120
            }
        ]
    }
}
```

El saldo se calcula teniendo en cuenta el saldo inicial, los eventos y las recargas.

### GET `/api/stats.php`

Devuelve estadísticas de gastos por usuario y periodo.

**Parámetros GET:**

| Parámetro | Descripción |
|---|---|
| `granularidad` | `mes`, `trimestre` o `año` |
| `desde` | Fecha inicial |
| `hasta` | Fecha final |

**Ejemplo de petición:**

`/api/stats.php?granularidad=mes&desde=2026-01-01&hasta=2026-12-31`

**Respuesta de ejemplo:**

```json
{
    "success": true,
    "data": {
        "granularidad": "mes",
        "periodos": [
            {
                "periodo": "2026-01",
                "usuario_1": 100,
                "usuario_2": 80
            }
        ]
    }
}
```

Para calcular las estadísticas se tienen en cuenta los efectos de suma y resta. Los eventos anulados quedan excluidos.

## 10. Administración

Los endpoints de administración permiten gestionar los usuarios desde el panel correspondiente.

Todos requieren una sesión activa y el rol `superadmin`.

### GET `/api/admin_usuarios_list.php`

Devuelve la lista de usuarios registrados.

### GET `/api/admin_usuario_get.php?id=`

Devuelve los datos de un usuario concreto.

### POST `/api/admin_usuario_create.php`

Crea un usuario desde el panel de administración.

### POST `/api/admin_usuario_update.php`

Modifica los datos de un usuario.

### POST `/api/admin_usuario_baja.php`

Realiza la baja lógica de un usuario, cambiando el campo `activo` a `FALSE` sin eliminar el registro de la base de datos.

### POST `/api/admin_pareja_set.php`

Permite crear o corregir manualmente la vinculación entre dos usuarios.

**Parámetros de entrada:**

```json
{
    "usuario_1_id": 1,
    "usuario_2_id": 2
}
```

**Validaciones:**

- Solo puede acceder un usuario con rol `superadmin`.
- Los dos usuarios deben existir y estar activos.
- Los usuarios no pueden ser la misma persona.
- Ninguno puede pertenecer a otra pareja.

## 11. Seguridad y control de acceso

En el Backend se realizan comprobaciones para evitar accesos no autorizados y modificaciones de datos que no corresponden al usuario.

Se utilizan las siguientes medidas:

- Las contraseñas se almacenan mediante `password_hash()`.
- Las contraseñas se comprueban mediante `password_verify()`.
- Las sesiones PHP permiten identificar al usuario autenticado.
- Las consultas a PostgreSQL utilizan sentencias preparadas de PDO.
- Los endpoints privados requieren autenticación.
- Los endpoints de administración requieren el rol `superadmin`.
- Cada usuario solo puede modificar sus propios predefinidos.
- Los eventos y las recargas solo pueden afectar a integrantes de la pareja.
- Los datos recibidos se validan antes de realizar operaciones en la base de datos.

## 12. Resumen de endpoints

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
| Eliminar recarga | POST | `recarga_delete.php` |
| Consultar saldos | GET | `saldos.php` |
| Consultar estadísticas | GET | `stats.php` |
| Listar usuarios | GET | `admin_usuarios_list.php` |
| Obtener usuario | GET | `admin_usuario_get.php` |
| Crear usuario | POST | `admin_usuario_create.php` |
| Modificar usuario | POST | `admin_usuario_update.php` |
| Dar de baja usuario | POST | `admin_usuario_baja.php` |
| Gestionar parejas | POST | `admin_pareja_set.php` |

---
