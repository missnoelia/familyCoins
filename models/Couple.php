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
                'INSERT INTO parejas (usuario_1_id, usuario_2_id)
                 VALUES (:usuario1, :usuario2)
                 RETURNING id'
            );
            $stmt->bindValue(':usuario1', $usuario1, PDO::PARAM_INT);
            $stmt->bindValue(':usuario2', $usuario2, PDO::PARAM_INT);
            $stmt->execute();

            $parejaId = (int) $stmt->fetchColumn();

            $this->db->commit();

            return $parejaId;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            if ($e instanceof PDOException && $e->getCode() === '23505') {
                throw new ApiException('Uno de los usuarios ya tiene pareja.', 409);
            }

            throw $e;
        }
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
        $sql = 'SELECT u.id, u.nombre, u.email, u.codigo, u.rol
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

        return $pareja ?: null;
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
}
