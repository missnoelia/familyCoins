
<?php

class Database {

    private $host = "localhost";
    private $port = "5432";
    private $dbname = "couplecoins_db";
    private $username = "postgres";
    private $password = "";

    public function connect() {

        try {

            $connection = new PDO(
                "pgsql:host=" . $this->host .
                ";port=" . $this->port .
                ";dbname=" . $this->dbname,
                $this->username,
                $this->password
            );

            $connection->setAttribute(
                PDO::ATTR_ERRMODE,
                PDO::ERRMODE_EXCEPTION
            );

            return $connection;

        } catch (PDOException $e) {

            die("Error de conexión: " . $e->getMessage());

        }
    }
}
