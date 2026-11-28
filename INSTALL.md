# Instalación

Guía para instalar y ejecutar FamilyCoins en local con **XAMPP** (Apache, PHP y
MariaDB) y **phpMyAdmin**.

## Requisitos

- [XAMPP](https://www.apachefriends.org/) con PHP 8.2 o superior y MariaDB 10.4
  o superior. Las extensiones `pdo_mysql` y `mysqli` vienen activadas por
  defecto.
- Un navegador para abrir phpMyAdmin.
- Opcional: [Git](https://git-scm.com/) para clonar el repositorio.

## 1. Colocar el proyecto

Copia o clona la carpeta del proyecto dentro de `htdocs` de XAMPP:

```
C:\xampp\htdocs\familyCoins
```

Abre el **Panel de Control de XAMPP** y pulsa **Start** en **Apache** y en
**MySQL**. El panel debe mostrar ambos servicios en verde.

## 2. Crear las bases de datos

El proyecto usa dos bases de datos:

| Base de datos | Uso |
|---|---|
| `familycoins` | Datos reales de la aplicación |
| `familycoins_test` | Pruebas automáticas (se vacía en cada ejecución) |

### Desde phpMyAdmin

1. Entra en [http://localhost/phpmyadmin](http://localhost/phpmyadmin).
2. Ve a la pestaña **Bases de datos**.
3. Crea `familycoins` con cotejamiento `utf8mb4_unicode_ci`.
4. Repite el proceso con `familycoins_test`.
5. Selecciona `familycoins`, abre la pestaña **Importar**, elige el archivo
   `db/schema.sql` y pulsa **Continuar**.
6. Repite la importación en `familycoins_test`.

### Desde la terminal

Con MySQL arrancado, desde la carpeta del proyecto:

```powershell
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE familycoins CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE familycoins_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
cmd /c "C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 familycoins < db\schema.sql"
cmd /c "C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 familycoins_test < db\schema.sql"
```

> Si las bases de datos ya existían y quieres empezar de cero, bórralas antes con
> `DROP DATABASE` y vuelve a crearlas. Esto elimina todos sus datos.

El script crea las tablas y los datos de ejemplo, incluidos los 11 artículos
globales de la tienda.

## 3. Configurar la conexión

La conexión está en `config/database.php`. Los valores por defecto coinciden con
una instalación estándar de XAMPP:

| Variable | Valor por defecto | Descripción |
|---|---|---|
| `DB_HOST` | `127.0.0.1` | Servidor de la base de datos |
| `DB_PORT` | `3306` | Puerto de MySQL/MariaDB |
| `DB_NAME` | `familycoins` | Base de datos de la aplicación |
| `DB_USER` | `root` | Usuario de MySQL |
| `DB_PASS` | *(vacío)* | Contraseña de MySQL |
| `STREAK_TIMEZONE` | `Europe/Madrid` | Zona horaria de racha, planta y bonos |

No hace falta editar el archivo: puedes sobrescribir estos valores con variables
de entorno antes de arrancar PHP. Por ejemplo, en PowerShell:

```powershell
$env:DB_USER = "root"
$env:DB_PASS = ""
```

Si tu usuario `root` tiene contraseña, define `DB_PASS` con ese valor o cambia el
valor por defecto en `config/database.php`.

## 4. Arrancar la aplicación

### Con Apache (recomendado)

Con Apache y MySQL en marcha, la API queda disponible en:

```
http://localhost/familyCoins/api/
```

Comprueba que responde abriendo:

```
http://localhost/familyCoins/api/health.php
```

Debe devolver un JSON con `"success": true` y `"base_de_datos": "ok"`.

### Con el servidor integrado de PHP

También puedes usar el servidor de desarrollo de PHP desde la carpeta del
proyecto:

```powershell
C:\xampp\php\php.exe -S 127.0.0.1:8099 -t .
```

La API queda entonces en `http://127.0.0.1:8099/api/`.

## 5. Ejecutar las pruebas

Las pruebas **vacían las tablas**, así que solo deben apuntar a
`familycoins_test`. Los scripts abortan si el nombre de la base de datos no
contiene `test`.

### Pruebas de modelos (160)

```powershell
$env:DB_NAME = "familycoins_test"
C:\xampp\php\php.exe tests\models_test.php
```

### Pruebas HTTP (175)

Primero arranca el servidor integrado de PHP (o usa Apache) y después:

```powershell
$env:DB_NAME = "familycoins_test"
$env:TEST_BASE_URL = "http://127.0.0.1:8099"
C:\xampp\php\php.exe tests\security_smoke.php
```

Con Apache, cambia `TEST_BASE_URL` por la URL correspondiente, por ejemplo
`http://localhost/familyCoins`.

Resultado esperado:

```
Pruebas: 160  Fallos: 0
Pruebas: 175  Fallos: 0
```

## 6. Comprobar la instalación en phpMyAdmin

En `familycoins` deben aparecer, entre otras, estas tablas:

- `usuarios`, `parejas`, `predefinidos`, `eventos`, `recargas`, `configuracion`
- `pareja_streaks`, `pareja_streak_checkins`
- `pareja_plantas`, `pareja_planta_cambios`
- `tienda_items`, `pareja_tareas`, `pareja_bonos`, `pareja_recuerdos`

La tabla `tienda_items` debe tener 11 filas con `pareja_id` a `NULL` (catálogo
global).

## Problemas frecuentes

- **`SQLSTATE[HY000] [2002]` o «Connection refused»:** MySQL/MariaDB no está
  arrancado. Pulsa **Start** en MySQL desde el panel de XAMPP.
- **`Unknown database 'familycoins'`:** no se ha creado la base de datos o el
  nombre no coincide con `DB_NAME`.
- **`Access denied for user 'root'`:** el usuario o la contraseña de MySQL no
  coinciden. Ajusta `DB_USER` y `DB_PASS`.
- **`could not find driver`:** falta la extensión `pdo_mysql`. Actívala en
  `php.ini` (`extension=pdo_mysql`) y reinicia Apache.
- **Acentos o emojis corruptos:** comprueba que la base de datos usa
  `utf8mb4_unicode_ci` y que importaste `db/schema.sql` con
  `--default-character-set=utf8mb4`.
- **Puerto 3306 ocupado:** otro servicio está usando el puerto de MySQL. Ciérralo
  o cambia `DB_PORT` y el puerto en `my.ini`.
- **Las pruebas se niegan a ejecutarse:** `DB_NAME` debe contener `test`. Usa
  siempre `familycoins_test`, nunca `familycoins`.
