# FamilyCoinsApp – Instalación

## 1. Requisitos

- PHP 8.0 o superior.
- PostgreSQL 14 o superior.
- Apache (XAMPP, WAMP, LAMP o similar).
- Extensiones PHP: `pdo_pgsql`, `mbstring` y `openssl`.
- Navegador web.

## 2. Descargar el proyecto

```bash
git clone URL_DEL_REPOSITORIO
cd familycoinsapp
```

## 3. Activar la extensión de PostgreSQL en PHP

En XAMPP, abrir `C:\xampp\php\php.ini` y quitar el punto y coma de esta línea:

```ini
extension=pdo_pgsql
```

Comprobar que queda activa:

```bash
php -m | findstr pgsql
```

Deben aparecer `pdo_pgsql` y `pgsql`. Si se usa Linux:

```bash
sudo apt install php-pgsql
sudo systemctl restart apache2
```

## 4. Crear la base de datos

Arrancar PostgreSQL y crear la base de datos:

```bash
psql -U postgres
```

```sql
CREATE DATABASE couplecoins_db;
\c couplecoins_db
\i db/schema.sql
\q
```

También se puede importar desde la terminal:

```bash
psql -U postgres -d couplecoins_db -f db/schema.sql
```

El esquema crea las tablas `usuarios`, `parejas`, `predefinidos`, `eventos`,
`recargas` y `configuracion`.

## 5. Configurar la conexión

El archivo `config/database.php` usa estos valores por defecto:

| Variable | Valor por defecto |
|---|---|
| `DB_HOST` | `localhost` |
| `DB_PORT` | `5432` |
| `DB_NAME` | `couplecoins_db` |
| `DB_USER` | `postgres` |
| `DB_PASS` | *(vacío)* |

Se pueden definir como variables de entorno. En Apache, por ejemplo:

```apache
SetEnv DB_HOST localhost
SetEnv DB_PORT 5432
SetEnv DB_NAME couplecoins_db
SetEnv DB_USER postgres
SetEnv DB_PASS mi_contraseña
```

Si no se usan variables de entorno, cambiar los valores por defecto directamente
en `config/database.php`.

## 6. Ejecutar el proyecto

Colocar el proyecto dentro del directorio del servidor web, por ejemplo:

```text
C:\xampp\htdocs\familycoinsapp\
```

Arrancar Apache y PostgreSQL y acceder a:

```text
http://localhost/familycoinsapp/
```

La landing pública (`index.php`), el login y el registro los maqueta el
Frontend; el Backend responde en `/api/`.

## 7. Crear un superadmin (opcional)

El panel de administración pertenece al sprint 5. Cuando se implemente, se puede
crear la cuenta con:

```bash
php -r "echo password_hash('Admin123', PASSWORD_DEFAULT);"
```

```sql
INSERT INTO usuarios (nombre, email, password_hash, codigo, rol, saldo_inicial, activo)
VALUES ('Admin', 'admin@familycoinsapp.local', 'HASH_GENERADO', 'ADM0001', 'superadmin', 0, TRUE);
```

## 8. Dominio local `familycoinsapp.local` (opcional)

Añadir al archivo `hosts`:

- Windows: `C:\Windows\System32\drivers\etc\hosts`
- Linux: `/etc/hosts`

```text
127.0.0.1 familycoinsapp.local
```

Configurar un VirtualHost de Apache:

```apache
<VirtualHost *:80>
    ServerName familycoinsapp.local
    DocumentRoot "C:/xampp/htdocs/familycoinsapp"
    <Directory "C:/xampp/htdocs/familycoinsapp">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Reiniciar Apache y acceder a `http://familycoinsapp.local/`.

## 9. Comprobar que funciona

1. `POST /api/register.php` crea un usuario y devuelve su código.
2. `POST /api/login.php` inicia sesión.
3. `GET /api/check_login.php` devuelve `logged: true` y `tiene_pareja: false`.
4. `GET /api/mi_codigo.php` devuelve el código personal.
5. `POST /api/pareja_vincular.php` vincula la pareja con el código de la otra
   cuenta.
6. A partir de ahí se pueden usar los endpoints de predefinidos, eventos y
   recargas.

Todas las respuestas son JSON. Los errores incluyen `success: false` y un
`message` legible.

## 10. Errores frecuentes

### Error de conexión a la base de datos

- Comprobar que el servicio de PostgreSQL está en ejecución.
- Revisar las credenciales y el nombre de la base de datos.
- Verificar que la base de datos `couplecoins_db` existe.
- Comprobar que la extensión `pdo_pgsql` está activada con `php -m`.

### Error 404 en las APIs

- Verificar que el archivo existe dentro de `/api/`.
- Comprobar que Apache tiene permisos de lectura sobre los archivos.
- Revisar las rutas que utiliza el Frontend; deben coincidir con las de
  `API.md`.

### Las sesiones no funcionan

- Comprobar que la cookie de sesión no está bloqueada por el navegador.
- Verificar que la carpeta de sesiones de PHP tiene permisos de escritura.
- En Linux, revisar `session.save_path` en `php.ini`.
- Las peticiones del Frontend deben incluir las cookies (`credentials: include`
  si se usa `fetch` desde otro origen).

### El código de pareja no se acepta

- Debe tener exactamente 3 letras y 4 dígitos, por ejemplo `ABC1234`.
- Comprobar que ninguno de los dos usuarios tiene ya una pareja.
- Verificar que la cuenta del código está activa.

### El panel de administración no carga

- Comprobar que el usuario tiene rol `superadmin`.
- Recordar que los endpoints de administración se implementan en el sprint 5.

### Los eventos o recargas devuelven 403

- El usuario debe tener pareja vinculada.
- Solo se pueden consultar o modificar datos del propio usuario o de su pareja.
