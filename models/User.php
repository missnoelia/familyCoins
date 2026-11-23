<?php
declare(strict_types=1);

class User
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Registra un usuario nuevo y devuelve su ID.
     *
     * Devuelve false si el email ya está registrado. Si el código generado
     * colisiona con otro usuario, se reintenta la operación.
     */
    public function register(string $nombre, string $email, string $password): int|false
    {
        $nombre = trim($nombre);
        $email = strtolower(trim($email));

        if ($this->getByEmail($email) !== null) {
            return false;
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $sql = 'INSERT INTO usuarios (nombre, email, password_hash, codigo, rol)
                VALUES (:nombre, :email, :password_hash, :codigo, :rol)
                RETURNING id';

        for ($intento = 0; $intento < 5; $intento++) {
            $codigo = $this->generateCode();
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
            $stmt->bindValue(':email', $email, PDO::PARAM_STR);
            $stmt->bindValue(':password_hash', $passwordHash, PDO::PARAM_STR);
            $stmt->bindValue(':codigo', $codigo, PDO::PARAM_STR);
            $stmt->bindValue(':rol', 'usuario', PDO::PARAM_STR);

            try {
                $stmt->execute();

                return (int) $stmt->fetchColumn();
            } catch (PDOException $e) {
                if ($e->getCode() !== '23505') {
                    throw $e;
                }

                // Puede ser un email duplicado (otra petición lo creó a la vez)
                // o una colisión del código. En el segundo caso se reintenta.
                if ($this->getByEmail($email) !== null) {
                    return false;
                }
            }
        }

        throw new RuntimeException('No se ha podido generar un código único para el usuario.');
    }

    /**
     * Comprueba las credenciales e inicia la sesión.
     *
     * @return array<string, mixed>|false
     */
    public function login(string $email, string $password): array|false
    {
        $user = $this->getByEmail($email);

        if ($user === null || !self::esActivo($user['activo'] ?? false)) {
            return false;
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            return false;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        session_regenerate_id(true);
        $_SESSION['usuario_id'] = (int) $user['id'];
        $_SESSION['usuario_rol'] = (string) $user['rol'];

        return $user;
    }

    /**
     * Cierra la sesión actual.
     */
    public function logout(): bool
    {
        return self::cerrarSesion();
    }

    /**
     * Cierra la sesión sin necesitar conexión a la base de datos.
     */
    public static function cerrarSesion(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getByEmail(string $email): ?array
    {
        $sql = 'SELECT * FROM usuarios WHERE email = :email LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':email', strtolower(trim($email)), PDO::PARAM_STR);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getById(int $id): ?array
    {
        $sql = 'SELECT id, nombre, email, codigo, rol, saldo_inicial, activo, created_at
                FROM usuarios
                WHERE id = :id
                LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getByCode(string $codigo): ?array
    {
        $sql = 'SELECT id, nombre, email, codigo, rol, activo
                FROM usuarios
                WHERE codigo = :codigo
                LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':codigo', strtoupper(trim($codigo)), PDO::PARAM_STR);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    /**
     * Genera un código único de 3 letras y 4 dígitos.
     */
    public function generateCode(): string
    {
        $letras = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

        for ($intento = 0; $intento < 50; $intento++) {
            $codigo = '';

            for ($i = 0; $i < 3; $i++) {
                $codigo .= $letras[random_int(0, strlen($letras) - 1)];
            }

            for ($i = 0; $i < 4; $i++) {
                $codigo .= (string) random_int(0, 9);
            }

            $stmt = $this->db->prepare('SELECT id FROM usuarios WHERE codigo = :codigo LIMIT 1');
            $stmt->bindValue(':codigo', $codigo, PDO::PARAM_STR);
            $stmt->execute();

            if ($stmt->fetchColumn() === false) {
                return $codigo;
            }
        }

        throw new RuntimeException('No se ha podido generar un código único.');
    }

    /**
     * Normaliza el campo activo, que PostgreSQL puede devolver como bool o
     * como texto según la configuración de PDO.
     */
    public static function esActivo(mixed $valor): bool
    {
        return $valor === true
            || $valor === 1
            || $valor === '1'
            || $valor === 't'
            || $valor === 'true';
    }
}
