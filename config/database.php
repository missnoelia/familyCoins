<?php
declare(strict_types=1);

/**
 * Conexión PDO con MySQL/MariaDB.
 *
 * Los valores por defecto coinciden con una instalación estándar de XAMPP.
 * Se pueden sobrescribir con variables de entorno para no tener que
 * modificarlos en el código en cada máquina.
 */
class Database
{
    private string $host;
    private string $port;
    private string $dbname;
    private string $username;
    private string $password;

    public function __construct()
    {
        $this->host = self::env('DB_HOST', '127.0.0.1');
        $this->port = self::env('DB_PORT', '3306');
        $this->dbname = self::env('DB_NAME', 'familycoins');
        $this->username = self::env('DB_USER', 'root');
        $this->password = self::env('DB_PASS', '');
    }

    private static function env(string $clave, string $defecto): string
    {
        $valor = getenv($clave);

        return $valor === false ? $defecto : $valor;
    }

    /**
     * Devuelve la conexión PDO o lanza una PDOException si no es posible.
     */
    public function connect(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $this->host,
            $this->port,
            $this->dbname
        );

        $opciones = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        // MySQL devuelve por defecto solo las filas realmente modificadas en un
        // UPDATE. Con FOUND_ROWS se comporta como PostgreSQL y devuelve las
        // filas encontradas, que es lo que esperan los modelos.
        if (defined('PDO::MYSQL_ATTR_FOUND_ROWS')) {
            $opciones[PDO::MYSQL_ATTR_FOUND_ROWS] = true;
        }

        return new PDO($dsn, $this->username, $this->password, $opciones);
    }

    /**
     * Indica si una excepción PDO es un conflicto de clave única.
     *
     * Acepta el código de PostgreSQL (23505) y el de MySQL/MariaDB (23000 con
     * driver 1062) para que los modelos no dependan del motor.
     */
    public static function esConflictoUnico(PDOException $e): bool
    {
        if ((string) $e->getCode() === '23505') {
            return true;
        }

        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        return (string) $e->getCode() === '23000'
            && in_array($driverCode, [1022, 1062], true);
    }
}
