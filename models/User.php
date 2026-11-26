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
     *
     * Los parámetros opcionales permiten crear cuentas desde el panel de
     * administración sin duplicar la lógica de generación de código.
     */
    public function register(
        string $nombre,
        string $email,
        string $password,
        string $rol = 'usuario',
        ?int $saldoInicial = null,
        bool $activo = true,
        ?string $fechaNacimiento = null
    ): int|false {
        $nombre = trim($nombre);
        $email = strtolower(trim($email));

        if ($this->getByEmail($email) !== null) {
            return false;
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $sql = 'INSERT INTO usuarios
                    (nombre, email, password_hash, codigo, rol, saldo_inicial, fecha_nacimiento, activo)
                VALUES
                    (:nombre, :email, :password_hash, :codigo, :rol, :saldo_inicial, :fecha_nacimiento, :activo)';

        for ($intento = 0; $intento < 5; $intento++) {
            $codigo = $this->generateCode();
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
            $stmt->bindValue(':email', $email, PDO::PARAM_STR);
            $stmt->bindValue(':password_hash', $passwordHash, PDO::PARAM_STR);
            $stmt->bindValue(':codigo', $codigo, PDO::PARAM_STR);
            $stmt->bindValue(':rol', $rol, PDO::PARAM_STR);

            if ($saldoInicial === null) {
                $stmt->bindValue(':saldo_inicial', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':saldo_inicial', $saldoInicial, PDO::PARAM_INT);
            }

            if ($fechaNacimiento === null) {
                $stmt->bindValue(':fecha_nacimiento', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':fecha_nacimiento', $fechaNacimiento, PDO::PARAM_STR);
            }

            $stmt->bindValue(':activo', $activo, PDO::PARAM_BOOL);

            try {
                $stmt->execute();

                return (int) $this->db->lastInsertId();
            } catch (PDOException $e) {
                if (!Database::esConflictoUnico($e)) {
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

        return $user ? self::normalizarUsuario($user) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getById(int $id): ?array
    {
        $sql = 'SELECT id, nombre, email, codigo, rol, saldo_inicial, fecha_nacimiento, activo, created_at
                FROM usuarios
                WHERE id = :id
                LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ? self::normalizarUsuario($user) : null;
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

        return $user ? self::normalizarUsuario($user) : null;
    }

    /**
     * Listado de usuarios para el panel de administración.
     *
     * @param array<string, mixed> $filtros
     * @return array{data: array<int, array<string, mixed>>, total: int}
     */
    public function adminList(array $filtros = []): array
    {
        $where = [];
        $parametros = [];

        if (!empty($filtros['q'])) {
            $where[] = '(LOWER(u.nombre) LIKE :q1 OR LOWER(u.email) LIKE :q2 OR LOWER(u.codigo) LIKE :q3)';
            $busqueda = '%' . strtolower(trim((string) $filtros['q'])) . '%';
            $parametros[':q1'] = $busqueda;
            $parametros[':q2'] = $busqueda;
            $parametros[':q3'] = $busqueda;
        }

        if (!empty($filtros['rol'])) {
            $where[] = 'u.rol = :rol';
            $parametros[':rol'] = (string) $filtros['rol'];
        }

        if (array_key_exists('activo', $filtros) && is_bool($filtros['activo'])) {
            $where[] = 'u.activo = ' . ($filtros['activo'] ? 'TRUE' : 'FALSE');
        }

        if (array_key_exists('con_pareja', $filtros)) {
            $where[] = ($filtros['con_pareja'] ? 'EXISTS' : 'NOT EXISTS')
                . ' (SELECT 1 FROM parejas p WHERE p.usuario_1_id = u.id OR p.usuario_2_id = u.id)';
        }

        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $sql = 'SELECT u.id, u.nombre, u.email, u.codigo, u.rol, u.saldo_inicial, u.fecha_nacimiento,
                       u.activo, u.created_at,
                       (SELECT p.id
                        FROM parejas p
                        WHERE p.usuario_1_id = u.id OR p.usuario_2_id = u.id
                        LIMIT 1) AS pareja_id,
                       (SELECT pu.id
                        FROM parejas p
                        INNER JOIN usuarios pu
                            ON pu.id = CASE
                                WHEN p.usuario_1_id = u.id THEN p.usuario_2_id
                                ELSE p.usuario_1_id
                            END
                        WHERE p.usuario_1_id = u.id OR p.usuario_2_id = u.id
                        LIMIT 1) AS pareja_usuario_id,
                       (SELECT pu.nombre
                        FROM parejas p
                        INNER JOIN usuarios pu
                            ON pu.id = CASE
                                WHEN p.usuario_1_id = u.id THEN p.usuario_2_id
                                ELSE p.usuario_1_id
                            END
                        WHERE p.usuario_1_id = u.id OR p.usuario_2_id = u.id
                        LIMIT 1) AS pareja_nombre
                FROM usuarios u'
                . $whereSql
                . ' ORDER BY u.id ASC
                    LIMIT :limite OFFSET :offset';

        $stmt = $this->db->prepare($sql);

        foreach ($parametros as $nombre => $valor) {
            $stmt->bindValue($nombre, $valor, PDO::PARAM_STR);
        }

        $stmt->bindValue(':limite', (int) $filtros['limite'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int) $filtros['offset'], PDO::PARAM_INT);
        $stmt->execute();

        $usuarios = array_map(
            static fn (array $usuario): array => self::normalizarUsuario($usuario),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM usuarios u' . $whereSql);

        foreach ($parametros as $nombre => $valor) {
            $stmt->bindValue($nombre, $valor, PDO::PARAM_STR);
        }

        $stmt->execute();

        return [
            'data' => $usuarios,
            'total' => (int) $stmt->fetchColumn(),
        ];
    }

    /**
     * Actualiza un usuario desde el panel de administración.
     */
    public function adminUpdate(
        int $id,
        string $nombre,
        string $email,
        string $rol,
        ?int $saldoInicial,
        bool $activo,
        ?string $password = null,
        ?string $fechaNacimiento = null
    ): bool {
        $campos = [
            'nombre = :nombre',
            'email = :email',
            'rol = :rol',
            'saldo_inicial = :saldo_inicial',
            'fecha_nacimiento = :fecha_nacimiento',
            'activo = :activo',
        ];

        $sql = 'UPDATE usuarios SET ' . implode(', ', $campos);

        if ($password !== null) {
            $sql .= ', password_hash = :password_hash';
        }

        $sql .= ' WHERE id = :id';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':nombre', trim($nombre), PDO::PARAM_STR);
        $stmt->bindValue(':email', strtolower(trim($email)), PDO::PARAM_STR);
        $stmt->bindValue(':rol', $rol, PDO::PARAM_STR);

        if ($saldoInicial === null) {
            $stmt->bindValue(':saldo_inicial', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':saldo_inicial', $saldoInicial, PDO::PARAM_INT);
        }

        if ($fechaNacimiento === null) {
            $stmt->bindValue(':fecha_nacimiento', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':fecha_nacimiento', $fechaNacimiento, PDO::PARAM_STR);
        }

        $stmt->bindValue(':activo', $activo, PDO::PARAM_BOOL);

        if ($password !== null) {
            $stmt->bindValue(':password_hash', password_hash($password, PASSWORD_DEFAULT), PDO::PARAM_STR);
        }

        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        try {
            $stmt->execute();
        } catch (PDOException $e) {
            if (Database::esConflictoUnico($e)) {
                throw new ApiException('El email ya está registrado.', 409);
            }

            throw $e;
        }

        return $this->getById($id) !== null;
    }

    /**
     * Da de baja lógica a un usuario.
     */
    public function adminDeactivate(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE usuarios
             SET activo = FALSE
             WHERE id = :id AND activo = TRUE'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Cuenta los superadministradores activos.
     */
    public function contarSuperadminsActivos(): int
    {
        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM usuarios WHERE rol = 'superadmin' AND activo = TRUE"
        );

        return (int) $stmt->fetchColumn();
    }

    /**
     * Devuelve el hash de la contraseña de un usuario.
     */
    public function getPasswordHash(int $id): ?string
    {
        $stmt = $this->db->prepare('SELECT password_hash FROM usuarios WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $hash = $stmt->fetchColumn();

        return $hash === false ? null : (string) $hash;
    }

    /**
     * Cambia la contraseña del propio usuario.
     */
    public function changePassword(int $id, string $passwordActual, string $passwordNueva): bool
    {
        $hash = $this->getPasswordHash($id);

        if ($hash === null || !password_verify($passwordActual, $hash)) {
            return false;
        }

        $stmt = $this->db->prepare('UPDATE usuarios SET password_hash = :hash WHERE id = :id');
        $stmt->bindValue(':hash', password_hash($passwordNueva, PASSWORD_DEFAULT), PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        return true;
    }

    /**
     * Actualiza la fecha de nacimiento del propio usuario.
     */
    public function updateBirthday(int $id, ?string $fecha): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE usuarios SET fecha_nacimiento = :fecha WHERE id = :id'
        );

        if ($fecha === null) {
            $stmt->bindValue(':fecha', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':fecha', $fecha, PDO::PARAM_STR);
        }

        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0 || $this->getById($id) !== null;
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

    /**
     * Normaliza los tipos de un usuario devuelto por la base de datos.
     *
     * @param array<string, mixed> $usuario
     * @return array<string, mixed>
     */
    public static function normalizarUsuario(array $usuario): array
    {
        foreach (['id', 'pareja_id', 'pareja_usuario_id'] as $campo) {
            if (array_key_exists($campo, $usuario)) {
                $usuario[$campo] = $usuario[$campo] === null ? null : (int) $usuario[$campo];
            }
        }

        if (array_key_exists('saldo_inicial', $usuario)) {
            $usuario['saldo_inicial'] = $usuario['saldo_inicial'] === null
                ? null
                : (int) $usuario['saldo_inicial'];
        }

        if (array_key_exists('activo', $usuario)) {
            $usuario['activo'] = self::esActivo($usuario['activo']);
        }

        return $usuario;
    }
}
