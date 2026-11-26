<?php
declare(strict_types=1);

class Couple
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Vincula a dos usuarios y devuelve el ID de la pareja.
     *
     * La operación se hace en una transacción y bloquea las filas de los
     * usuarios para evitar que dos peticiones simultáneas creen parejas
     * duplicadas.
     */
    public function link(int $usuarioId, int $otroUsuarioId): int
    {
        if ($usuarioId === $otroUsuarioId) {
            throw new ApiException('No puedes vincularte contigo mismo.', 400);
        }

        $this->db->beginTransaction();

        try {
            // Bloquea ambos usuarios para que la comprobación y el insert sean atómicos.
            $stmt = $this->db->prepare(
                'SELECT id
                 FROM usuarios
                 WHERE id IN (:usuario1, :usuario2) AND activo = TRUE
                 FOR UPDATE'
            );
            $stmt->bindValue(':usuario1', $usuarioId, PDO::PARAM_INT);
            $stmt->bindValue(':usuario2', $otroUsuarioId, PDO::PARAM_INT);
            $stmt->execute();

            $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

            if (count(array_unique($ids)) !== 2) {
                throw new ApiException('Uno de los usuarios no existe o no está activo.', 404);
            }

            if ($this->hasPartner($usuarioId) || $this->hasPartner($otroUsuarioId)) {
                throw new ApiException('Uno de los usuarios ya tiene pareja.', 409);
            }

            // Se guarda siempre en el mismo orden para que la pareja sea única.
            $usuario1 = min($usuarioId, $otroUsuarioId);
            $usuario2 = max($usuarioId, $otroUsuarioId);

            $stmt = $this->db->prepare(
                'INSERT INTO parejas (usuario_1_id, usuario_2_id, fecha_aniversario)
                 VALUES (:usuario1, :usuario2, :fecha_aniversario)'
            );
            $stmt->bindValue(':usuario1', $usuario1, PDO::PARAM_INT);
            $stmt->bindValue(':usuario2', $usuario2, PDO::PARAM_INT);
            $stmt->bindValue(':fecha_aniversario', api_hoy()->format('Y-m-d'), PDO::PARAM_STR);
            $stmt->execute();

            $parejaId = (int) $this->db->lastInsertId();

            $this->db->commit();

            return $parejaId;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            if ($e instanceof PDOException && Database::esConflictoUnico($e)) {
                throw new ApiException('Uno de los usuarios ya tiene pareja.', 409);
            }

            throw $e;
        }
    }

    /**
     * Crea o corrige una pareja desde el panel de administración.
     *
     * Si alguno de los usuarios ya estaba emparejado con otra persona, se
     * eliminan esas parejas para dejar la nueva relación canónica.
     *
     * @return array{id: int, creada: bool}
     */
    public function adminSet(int $usuario1, int $usuario2): array
    {
        if ($usuario1 === $usuario2) {
            throw new ApiException('No puedes emparejar a un usuario consigo mismo.', 400);
        }

        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare(
                'SELECT id, activo
                 FROM usuarios
                 WHERE id IN (:usuario1, :usuario2)
                 FOR UPDATE'
            );
            $stmt->bindValue(':usuario1', $usuario1, PDO::PARAM_INT);
            $stmt->bindValue(':usuario2', $usuario2, PDO::PARAM_INT);
            $stmt->execute();

            $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($usuarios) !== 2) {
                throw new ApiException('Uno de los usuarios no existe.', 404);
            }

            foreach ($usuarios as $usuario) {
                if (!User::esActivo($usuario['activo'] ?? false)) {
                    throw new ApiException('Uno de los usuarios no está activo.', 409);
                }
            }

            $menor = min($usuario1, $usuario2);
            $mayor = max($usuario1, $usuario2);
            $usuario1 = $menor;
            $usuario2 = $mayor;

            $stmt = $this->db->prepare(
                'SELECT id
                 FROM parejas
                 WHERE (usuario_1_id = :a AND usuario_2_id = :b)
                    OR (usuario_1_id = :c AND usuario_2_id = :d)
                 LIMIT 1'
            );
            $stmt->bindValue(':a', $usuario1, PDO::PARAM_INT);
            $stmt->bindValue(':b', $usuario2, PDO::PARAM_INT);
            $stmt->bindValue(':c', $usuario2, PDO::PARAM_INT);
            $stmt->bindValue(':d', $usuario1, PDO::PARAM_INT);
            $stmt->execute();

            $existente = $stmt->fetchColumn();

            if ($existente !== false) {
                $this->db->commit();

                return ['id' => (int) $existente, 'creada' => false];
            }

            // Elimina cualquier pareja anterior de cualquiera de los dos.
            $stmt = $this->db->prepare(
                'DELETE FROM parejas
                 WHERE usuario_1_id IN (:a, :b)
                    OR usuario_2_id IN (:c, :d)'
            );
            $stmt->bindValue(':a', $usuario1, PDO::PARAM_INT);
            $stmt->bindValue(':b', $usuario2, PDO::PARAM_INT);
            $stmt->bindValue(':c', $usuario1, PDO::PARAM_INT);
            $stmt->bindValue(':d', $usuario2, PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $this->db->prepare(
                'INSERT INTO parejas (usuario_1_id, usuario_2_id, fecha_aniversario)
                 VALUES (:usuario1, :usuario2, :fecha_aniversario)'
            );
            $stmt->bindValue(':usuario1', $usuario1, PDO::PARAM_INT);
            $stmt->bindValue(':usuario2', $usuario2, PDO::PARAM_INT);
            $stmt->bindValue(':fecha_aniversario', api_hoy()->format('Y-m-d'), PDO::PARAM_STR);
            $stmt->execute();

            $parejaId = (int) $this->db->lastInsertId();

            $this->db->commit();

            return ['id' => $parejaId, 'creada' => true];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            if ($e instanceof PDOException && Database::esConflictoUnico($e)) {
                throw new ApiException('No se ha podido crear la pareja por un conflicto de datos.', 409);
            }

            throw $e;
        }
    }

    /**
     * Listado de parejas para el panel de administración.
     *
     * @param array<string, mixed> $filtros
     * @return array{data: array<int, array<string, mixed>>, total: int}
     */
    public function adminList(array $filtros = []): array
    {
        $where = [];
        $parametros = [];

        if (!empty($filtros['q'])) {
            $where[] = '(LOWER(u1.nombre) LIKE :q1
                      OR LOWER(u1.email) LIKE :q2
                      OR LOWER(u1.codigo) LIKE :q3
                      OR LOWER(u2.nombre) LIKE :q4
                      OR LOWER(u2.email) LIKE :q5
                      OR LOWER(u2.codigo) LIKE :q6)';

            $busqueda = '%' . strtolower(trim((string) $filtros['q'])) . '%';

            foreach ([':q1', ':q2', ':q3', ':q4', ':q5', ':q6'] as $parametro) {
                $parametros[$parametro] = $busqueda;
            }
        }

        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $campos = 'p.id, p.fecha_aniversario, p.created_at,
                   u1.id AS u1_id, u1.nombre AS u1_nombre, u1.email AS u1_email,
                   u1.codigo AS u1_codigo, u1.rol AS u1_rol,
                   u1.saldo_inicial AS u1_saldo_inicial, u1.fecha_nacimiento AS u1_fecha_nacimiento,
                   u1.activo AS u1_activo,
                   u2.id AS u2_id, u2.nombre AS u2_nombre, u2.email AS u2_email,
                   u2.codigo AS u2_codigo, u2.rol AS u2_rol,
                   u2.saldo_inicial AS u2_saldo_inicial, u2.fecha_nacimiento AS u2_fecha_nacimiento,
                   u2.activo AS u2_activo';

        $desde = ' FROM parejas p
                   INNER JOIN usuarios u1 ON u1.id = p.usuario_1_id
                   INNER JOIN usuarios u2 ON u2.id = p.usuario_2_id';

        $sql = 'SELECT ' . $campos . $desde . $whereSql
            . ' ORDER BY p.id ASC LIMIT :limite OFFSET :offset';

        $stmt = $this->db->prepare($sql);

        foreach ($parametros as $nombre => $valor) {
            $stmt->bindValue($nombre, $valor, PDO::PARAM_STR);
        }

        $stmt->bindValue(':limite', (int) $filtros['limite'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int) $filtros['offset'], PDO::PARAM_INT);
        $stmt->execute();

        $parejas = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $parejas[] = [
                'id' => (int) $fila['id'],
                'fecha_aniversario' => $fila['fecha_aniversario'],
                'created_at' => $fila['created_at'],
                'usuario_1' => self::normalizarMiembro($fila, 'u1'),
                'usuario_2' => self::normalizarMiembro($fila, 'u2'),
            ];
        }

        $stmt = $this->db->prepare('SELECT COUNT(*)' . $desde . $whereSql);

        foreach ($parametros as $nombre => $valor) {
            $stmt->bindValue($nombre, $valor, PDO::PARAM_STR);
        }

        $stmt->execute();

        return [
            'data' => $parejas,
            'total' => (int) $stmt->fetchColumn(),
        ];
    }

    /**
     * Obtiene una pareja por su ID para el panel de administración.
     *
     * @return array<string, mixed>|null
     */
    public function adminGet(int $id): ?array
    {
        $sql = 'SELECT p.id, p.fecha_aniversario, p.created_at,
                       u1.id AS u1_id, u1.nombre AS u1_nombre, u1.email AS u1_email,
                       u1.codigo AS u1_codigo, u1.rol AS u1_rol,
                       u1.saldo_inicial AS u1_saldo_inicial, u1.fecha_nacimiento AS u1_fecha_nacimiento,
                       u1.activo AS u1_activo,
                       u2.id AS u2_id, u2.nombre AS u2_nombre, u2.email AS u2_email,
                       u2.codigo AS u2_codigo, u2.rol AS u2_rol,
                       u2.saldo_inicial AS u2_saldo_inicial, u2.fecha_nacimiento AS u2_fecha_nacimiento,
                       u2.activo AS u2_activo
                FROM parejas p
                INNER JOIN usuarios u1 ON u1.id = p.usuario_1_id
                INNER JOIN usuarios u2 ON u2.id = p.usuario_2_id
                WHERE p.id = :id
                LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($fila === false) {
            return null;
        }

        return [
            'id' => (int) $fila['id'],
            'fecha_aniversario' => $fila['fecha_aniversario'],
            'created_at' => $fila['created_at'],
            'usuario_1' => self::normalizarMiembro($fila, 'u1'),
            'usuario_2' => self::normalizarMiembro($fila, 'u2'),
        ];
    }

    /**
     * Elimina una pareja por su ID.
     */
    public function adminDelete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM parejas WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Indica si el usuario ya tiene pareja.
     */
    public function hasPartner(int $usuarioId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT id
             FROM parejas
             WHERE usuario_1_id = :usuario1 OR usuario_2_id = :usuario2
             LIMIT 1'
        );
        $stmt->bindValue(':usuario1', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':usuario2', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Devuelve los datos de la pareja del usuario o null si no tiene.
     *
     * @return array<string, mixed>|null
     */
    public function getPartner(int $usuarioId): ?array
    {
        $sql = 'SELECT u.id, u.nombre, u.email, u.codigo, u.rol, u.fecha_nacimiento
                FROM parejas p
                INNER JOIN usuarios u
                    ON u.id = CASE
                        WHEN p.usuario_1_id = :usuario1 THEN p.usuario_2_id
                        ELSE p.usuario_1_id
                    END
                WHERE p.usuario_1_id = :usuario2 OR p.usuario_2_id = :usuario3
                LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':usuario1', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':usuario2', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':usuario3', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        $pareja = $stmt->fetch(PDO::FETCH_ASSOC);

        return $pareja ? User::normalizarUsuario($pareja) : null;
    }

    /**
     * Alias usado por los endpoints de estado de pareja.
     *
     * @return array<string, mixed>|null
     */
    public function getStatus(int $usuarioId): ?array
    {
        return $this->getPartner($usuarioId);
    }

    /**
     * Datos básicos de la pareja del usuario (incluido el aniversario).
     *
     * @return array{id: int, usuario_1_id: int, usuario_2_id: int, fecha_aniversario: ?string}|null
     */
    public function getParejaDeUsuario(int $usuarioId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, usuario_1_id, usuario_2_id, fecha_aniversario
             FROM parejas
             WHERE usuario_1_id = :usuario1 OR usuario_2_id = :usuario2
             LIMIT 1'
        );
        $stmt->bindValue(':usuario1', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':usuario2', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        $pareja = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($pareja === false) {
            return null;
        }

        return [
            'id' => (int) $pareja['id'],
            'usuario_1_id' => (int) $pareja['usuario_1_id'],
            'usuario_2_id' => (int) $pareja['usuario_2_id'],
            'fecha_aniversario' => $pareja['fecha_aniversario'],
        ];
    }

    /**
     * Actualiza la fecha de aniversario de la pareja del usuario.
     *
     * @return array{id: int, usuario_1_id: int, usuario_2_id: int, fecha_aniversario: ?string}|null
     */
    public function actualizarAniversario(int $usuarioId, ?string $fecha): ?array
    {
        $pareja = $this->getParejaDeUsuario($usuarioId);

        if ($pareja === null) {
            return null;
        }

        $stmt = $this->db->prepare(
            'UPDATE parejas SET fecha_aniversario = :fecha WHERE id = :id'
        );

        if ($fecha === null) {
            $stmt->bindValue(':fecha', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':fecha', $fecha, PDO::PARAM_STR);
        }

        $stmt->bindValue(':id', $pareja['id'], PDO::PARAM_INT);
        $stmt->execute();

        return $this->getParejaDeUsuario($usuarioId);
    }

    /**
     * Comprueba que el otro usuario es el propio usuario o su pareja.
     */
    public function esDeLaPareja(int $usuarioId, int $otroUsuarioId): bool
    {
        if ($usuarioId === $otroUsuarioId) {
            return true;
        }

        $stmt = $this->db->prepare(
            'SELECT id
             FROM parejas
             WHERE (usuario_1_id = :usuario1 AND usuario_2_id = :usuario2)
                OR (usuario_1_id = :usuario3 AND usuario_2_id = :usuario4)
             LIMIT 1'
        );
        $stmt->bindValue(':usuario1', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':usuario2', $otroUsuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':usuario3', $otroUsuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':usuario4', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchColumn() !== false;
    }

    /**
     * @param array<string, mixed> $fila
     * @return array<string, mixed>
     */
    private static function normalizarMiembro(array $fila, string $prefijo): array
    {
        return [
            'id' => (int) $fila[$prefijo . '_id'],
            'nombre' => $fila[$prefijo . '_nombre'],
            'email' => $fila[$prefijo . '_email'],
            'codigo' => $fila[$prefijo . '_codigo'],
            'rol' => $fila[$prefijo . '_rol'],
            'saldo_inicial' => $fila[$prefijo . '_saldo_inicial'] === null
                ? null
                : (int) $fila[$prefijo . '_saldo_inicial'],
            'fecha_nacimiento' => $fila[$prefijo . '_fecha_nacimiento'] ?? null,
            'activo' => User::esActivo($fila[$prefijo . '_activo']),
        ];
    }
}
