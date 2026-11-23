# Arquitectura

Descripción de cómo está organizado CoupleCoins.

## Visión general

FamilyCoins es una aplicación web con tres partes:

1. **Frontend:** la interfaz que ve el usuario en el navegador.
2. **Backend:** la API que contiene la lógica y accede a los datos.
3. **Base de datos:** PostgreSQL, donde se guarda toda la información.

```
Navegador (Frontend)  →  API REST (Backend)  →  PostgreSQL
```

El frontend nunca habla directamente con la base de datos: siempre pasa por el backend.

## Tecnologías

| Parte | Tecnología |
|---|---|
| Frontend | (por ejemplo: React, HTML/CSS/JS) |
| Backend | (por ejemplo: Node.js + Express) |
| Base de datos | PostgreSQL |
| Control de versiones | Git y GitHub |

## Estructura de carpetas

```
familyCoins/
├── backend/          # API y lógica del servidor
├── frontend/         # Interfaz de usuario
├── database.sql      # Script para crear las tablas
├── README.md
├── INSTALL.md
├── API.md
├── ARCHITECTURE.md
└── DB_DESIGN.md
```

## Backend

Explica aquí cómo se organiza por dentro. Por ejemplo:

- **routes/:** define las URLs de la API.
- **controllers/:** recibe las peticiones y devuelve respuestas.
- **models/ o services/:** consultas a la base de datos y lógica.
- **middleware/:** comprobaciones previas, como la autenticación.

## Frontend

- **pages/ o views/:** pantallas de la aplicación.
- **components/:** piezas reutilizables.
- **services/:** llamadas a la API.

## Autenticación y roles

- El usuario inicia sesión con email y contraseña.
- Las contraseñas se guardan cifradas (`password_hash`), nunca en texto plano.
- Cada usuario tiene un `rol`, que determina qué acciones puede hacer.

## Flujo de datos de ejemplo

Registrar un evento:

1. El usuario rellena el formulario en el frontend.
2. El frontend envía la petición a la API.
3. El backend comprueba que el usuario está autenticado.
4. El backend guarda el evento en la tabla `eventos`.
5. El backend responde y el frontend actualiza la pantalla.

## Base de datos

El diseño completo de tablas y relaciones está en [DB_DESIGN.md](DB_DESIGN.md).

## Organización del trabajo en Git

| Rama | Uso |
|---|---|
| main | Versión estable y final |
| develop | Integración del trabajo de todo el equipo |
| feature/backend | Desarrollo del backend |
| feature/frontend | Desarrollo del frontend |

El trabajo se hace en las ramas `feature/*` y se une a `develop` mediante Pull Requests.