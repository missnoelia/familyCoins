<?php
declare(strict_types=1);

/**
 * Conexión PDO con PostgreSQL.
 *
 * Las credenciales se pueden sobrescribir con variables de entorno para no
 * tener que modificarlas en el código en cada máquina.
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
        $this->host = self::env('DB_HOST', 'localhost');
        $this->port = self::env('DB_PORT', '5432');
        $this->dbname = self::env('DB_NAME', 'couplecoins_db');
        $this->username = self::env('DB_USER', 'postgres');
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
            'pgsql:host=%s;port=%s;dbname=%s',
            $this->host,
            $this->port,
            $this->dbname
        );

        return new PDO(
            $dsn,
            $this->username,
            $this->password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }
}
