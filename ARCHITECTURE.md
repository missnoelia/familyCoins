# Arquitectura

Descripción de cómo está organizado FamilyCoins.

## Visión general

FamilyCoins es una aplicación web con tres partes:

1. **Frontend:** la interfaz que ve el usuario en el navegador.
2. **Backend:** la API que contiene la lógica y accede a los datos.
3. **Base de datos:** MySQL/MariaDB, donde se guarda toda la información.

```
Navegador (Frontend)  →  API REST (Backend)  →  MySQL/MariaDB
```

El frontend nunca habla directamente con la base de datos: siempre pasa por el
backend.

## Tecnologías

| Parte | Tecnología |
|---|---|
| Frontend | HTML, CSS y JavaScript |
| Backend | PHP 8.2 (sin frameworks), PDO y sesiones |
| Base de datos | MySQL/MariaDB con `utf8mb4` |
| Control de versiones | Git y GitHub |

## Estructura de carpetas

```
familyCoins/
├── admin/            # Panel de administración
├── api/              # Endpoints JSON de la API
├── config/           # Arranque común y conexión a la base de datos
├── controllers/      # Lógica de las pantallas
├── css/              # Estilos
├── db/               # Esquema SQL (db/schema.sql)
├── js/               # JavaScript del frontend
├── models/           # Acceso a datos y reglas de negocio
├── tests/            # Pruebas de modelos y HTTP
├── app.php           # Aplicación principal
├── index.php         # Entrada del frontend
├── README.md
├── INSTALL.md
├── API.md
├── ARCHITECTURE.md
└── DB_DESIGN.md
```

## Backend

- **api/:** un archivo por endpoint. Valida la petición, llama al modelo
  correspondiente y devuelve JSON con `api_responder()` o `api_error()`.
- **config/bootstrap.php:** arranque común. Configura la sesión, las cabeceras
  JSON, las validaciones compartidas y las respuestas de error.
- **config/database.php:** clase `Database`. Crea la conexión PDO con MySQL/MariaDB
  y ofrece el helper `esConflictoUnico()` para detectar duplicados.
- **models/:** consultas SQL y reglas de negocio. Cada modelo recibe la conexión
  PDO y encapsula su tabla.
- **controllers/:** lógica de las pantallas del frontend.
- **tests/:** `models_test.php` (pruebas de modelos) y `security_smoke.php`
  (pruebas HTTP contra un servidor local).

Los modelos principales son:

| Modelo | Responsabilidad |
|---|---|
| `User` | Usuarios, registro, login y administración |
| `Couple` | Parejas y aniversario |
| `Predefined` | Eventos predefinidos |
| `Event` | Eventos e impacto en el saldo |
| `Recharge` | Recargas de coins |
| `Balance` | Saldos individuales y conjunto |
| `Stats` | Estadísticas por periodo |
| `Streak` | Racha compartida de la pareja |
| `Plant` | Dos plantas por pareja (una por miembro) y sus etapas |
| `Shop` | Tienda y tareas de la pareja |
| `Bonus` | Bonos de cumpleaños y aniversario |
| `Memory` | Recuerdos compartidos de la pareja |
| `Achievement` | Logros calculados al vuelo con datos reales |

## Frontend

- **index.php, login.php, registro.php, vincular.php:** pantallas principales.
- **admin/:** panel de administración.
- **js/:** llamadas a la API y comportamiento de la interfaz.
- **css/:** estilos.

## Autenticación y roles

- El usuario inicia sesión con email y contraseña.
- Las contraseñas se guardan cifradas (`password_hash`), nunca en texto plano.
- Cada usuario tiene un `rol`, que determina qué acciones puede hacer.
- Los endpoints de administración exigen el rol `superadmin`.
- La sesión se guarda en una cookie `HttpOnly` y `SameSite=Lax`, y el ID se
  regenera al iniciar sesión y al cambiar la contraseña.

## Flujo de datos de ejemplo

Registrar un evento:

1. El usuario rellena el formulario en el frontend.
2. El frontend envía la petición a la API.
3. El backend comprueba que el usuario está autenticado.
4. El backend guarda el evento en la tabla `eventos`.
5. El backend responde y el frontend actualiza la pantalla.

## Base de datos

El diseño completo de tablas y relaciones está en [DB_DESIGN.md](DB_DESIGN.md) y
el script canónico en [db/schema.sql](db/schema.sql).

## Organización del trabajo en Git

| Rama | Uso |
|---|---|
| main | Versión estable y final |
| develop | Integración del trabajo de todo el equipo |
| feature/backend | Desarrollo del backend |
| feature/frontend | Desarrollo del frontend |

El trabajo se hace en las ramas `feature/*` y se une a `develop` mediante Pull
Requests.
