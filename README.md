# FamilyCoins

Aplicación web para gestionar un sistema de **coins** entre los miembros de una
pareja o familia. Cada persona registra eventos, recargas y tareas, y el sistema
calcula su saldo y las estadísticas de la pareja.

Proyecto 2 del ciclo DAW.

## Funcionalidades

- Registro e inicio de sesión con distintos roles (`usuario` y `superadmin`).
- Vinculación de parejas mediante un código personal.
- Predefinidos propios para registrar eventos rápidamente.
- Eventos con coste y efecto (`resta` o `suma`), con historial y anulación.
- Recargas de coins.
- Saldos individuales y conjunto de la pareja.
- Estadísticas por mes, trimestre o año.
- Racha compartida: el día cuenta cuando entran los dos miembros.
- Dos plantas, una por cada miembro, que crecen con la racha y se degradan si se
  rompe.
- Tienda de pareja con recompensas, permisos y tareas confirmadas.
- Bonos automáticos de cumpleaños y aniversario.
- Recuerdos compartidos de la pareja.
- Logros calculados con datos reales (primer cuidado, rachas y colección).
- Panel de administración de usuarios, parejas, eventos y recargas.

## Tecnologías

- **Backend:** PHP 8.2 (sin frameworks), PDO y sesiones.
- **Base de datos:** MySQL/MariaDB (`utf8mb4`).
- **Frontend:** HTML, CSS y JavaScript.
- **Entorno local:** XAMPP (Apache + PHP + MariaDB + phpMyAdmin).

## Instalación rápida

Los pasos completos están en [INSTALL.md](INSTALL.md).

1. Copia el proyecto en `C:\xampp\htdocs\familyCoins`.
2. Arranca **Apache** y **MySQL** desde XAMPP.
3. Crea `familycoins` y `familycoins_test` en phpMyAdmin con cotejamiento
   `utf8mb4_unicode_ci`.
4. Importa `db/schema.sql` en las dos bases de datos.
5. Abre `http://localhost/familyCoins/api/health.php`.

La conexión usa por defecto `127.0.0.1:3306`, usuario `root` y contraseña vacía,
y se puede ajustar con las variables de entorno `DB_HOST`, `DB_PORT`, `DB_NAME`,
`DB_USER` y `DB_PASS`.

## Pruebas

```powershell
$env:DB_NAME = "familycoins_test"
C:\xampp\php\php.exe tests\models_test.php

# Con el servidor arrancado en 127.0.0.1:8099
$env:TEST_BASE_URL = "http://127.0.0.1:8099"
C:\xampp\php\php.exe tests\security_smoke.php
```

Resultado esperado: **160 pruebas de modelos** y **175 pruebas HTTP**, sin
fallos.

## Documentación

| Documento | Contenido |
|---|---|
| [INSTALL.md](INSTALL.md) | Instalación y ejecución con XAMPP y phpMyAdmin |
| [API.md](API.md) | Endpoints de la API |
| [ARCHITECTURE.md](ARCHITECTURE.md) | Organización y estructura del proyecto |
| [DB_DESIGN.md](DB_DESIGN.md) | Diseño de tablas, campos y relaciones |

## Estructura del proyecto

```
familyCoins/
├── admin/            # Panel de administración
├── api/              # Endpoints JSON de la API
├── config/           # Arranque común y conexión a la base de datos
├── controllers/      # Lógica de las pantallas
├── css/              # Estilos
├── db/               # Esquema SQL
├── js/               # JavaScript del frontend
├── models/           # Acceso a datos y reglas de negocio
├── tests/            # Pruebas de modelos y HTTP
├── app.php           # Aplicación principal
├── index.php         # Entrada del frontend
└── README.md
```

## Flujo de trabajo en Git

- `main`: versión estable.
- `develop`: integración del trabajo del equipo.
- `feature/backend` y `feature/frontend`: desarrollo de cada parte.

Los cambios se unen a `develop` mediante Pull Requests.

## Autoría

- missnoelia
- dariakoba
