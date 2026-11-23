<?php
declare(strict_types=1);

class Predefined
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Obtiene los predefinidos de un usuario.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(int $usuarioId): array
    {
        $sql = 'SELECT id, usuario_id, nombre, coste, efecto
                FROM predefinidos
                WHERE usuario_id = :usuario_id
                ORDER BY nombre ASC, id ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene un predefinido concreto comprobando que sea del usuario.
     *
     * @return array<string, mixed>|null
     */
    public function getById(int $id, int $usuarioId): ?array
    {
        $sql = 'SELECT id, usuario_id, nombre, coste, efecto
                FROM predefinidos
                WHERE id = :id AND usuario_id = :usuario_id
                LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        $predefinido = $stmt->fetch(PDO::FETCH_ASSOC);

        return $predefinido ?: null;
    }

    /**
     * Comprueba si el usuario ya tiene un predefinido con ese nombre.
     *
     * La comparación no distingue mayúsculas ni minúsculas.
     */
    public function nombreExiste(int $usuarioId, string $nombre, int $excluirId = 0): bool
    {
        $sql = 'SELECT id
                FROM predefinidos
                WHERE usuario_id = :usuario_id
                  AND LOWER(nombre) = LOWER(:nombre)
                  AND id <> :excluir_id
                LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':nombre', trim($nombre), PDO::PARAM_STR);
        $stmt->bindValue(':excluir_id', $excluirId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Crea un predefinido propio.
     */
    public function create(int $usuarioId, string $nombre, int $coste, string $efecto): int
    {
        $sql = 'INSERT INTO predefinidos (usuario_id, nombre, coste, efecto)
                VALUES (:usuario_id, :nombre, :coste, :efecto)
                RETURNING id';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':nombre', trim($nombre), PDO::PARAM_STR);
        $stmt->bindValue(':coste', $coste, PDO::PARAM_INT);
        $stmt->bindValue(':efecto', $efecto, PDO::PARAM_STR);

        try {
            $stmt->execute();
        } catch (PDOException $e) {
            if ($e->getCode() === '23505') {
                throw new ApiException('Ya existe un predefinido con ese nombre.', 409);
            }

            throw $e;
        }

        return (int) $stmt->fetchColumn();
    }

    /**
     * Modifica un predefinido propio.
     */
    public function update(int $id, int $usuarioId, string $nombre, int $coste, string $efecto): bool
    {
        $sql = 'UPDATE predefinidos
                SET nombre = :nombre, coste = :coste, efecto = :efecto
                WHERE id = :id AND usuario_id = :usuario_id';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':nombre', trim($nombre), PDO::PARAM_STR);
        $stmt->bindValue(':coste', $coste, PDO::PARAM_INT);
        $stmt->bindValue(':efecto', $efecto, PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);

        try {
            $stmt->execute();
        } catch (PDOException $e) {
            if ($e->getCode() === '23505') {
                throw new ApiException('Ya existe un predefinido con ese nombre.', 409);
            }

            throw $e;
        }

        return $stmt->rowCount() > 0;
    }

    /**
     * Elimina un predefinido propio.
     *
     * Antes de borrarlo se desvinculan los eventos que lo usaban para no romper
     * la clave foránea y conservar el historial.
     */
    public function delete(int $id, int $usuarioId): bool
    {
        $this->db->beginTransaction();

        try {
            if ($this->getById($id, $usuarioId) === null) {
                $this->db->rollBack();

                return false;
            }

            $stmt = $this->db->prepare(
                'UPDATE eventos
                 SET predefinido_id = NULL
                 WHERE predefinido_id = :predefinido_id'
            );
            $stmt->bindValue(':predefinido_id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $this->db->prepare(
                'DELETE FROM predefinidos
                 WHERE id = :id AND usuario_id = :usuario_id'
            );
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
            $stmt->execute();

            $eliminado = $stmt->rowCount() > 0;

            $this->db->commit();

            return $eliminado;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }
}
