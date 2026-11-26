<?php
declare(strict_types=1);

class Recharge
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Consulta las recargas de la pareja.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(int $usuarioId): array
    {
        $sql = 'SELECT r.*, u.nombre AS usuario_nombre
                FROM recargas r
                INNER JOIN usuarios u ON u.id = r.usuario_id
                WHERE (
                    r.usuario_id = :usuario1
                    OR r.usuario_id = (
                        SELECT CASE
                            WHEN p.usuario_1_id = :usuario2 THEN p.usuario_2_id
                            ELSE p.usuario_1_id
                        END
                        FROM parejas p
                        WHERE p.usuario_1_id = :usuario3 OR p.usuario_2_id = :usuario4
                        LIMIT 1
                    )
                )
                ORDER BY r.fecha DESC, r.id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':usuario1', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':usuario2', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':usuario3', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':usuario4', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene una recarga por su ID.
     *
     * @return array<string, mixed>|null
     */
    public function getById(int $id): ?array
    {
        $sql = 'SELECT r.*, u.nombre AS usuario_nombre
                FROM recargas r
                INNER JOIN usuarios u ON u.id = r.usuario_id
                WHERE r.id = :id
                LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $recarga = $stmt->fetch(PDO::FETCH_ASSOC);

        return $recarga ?: null;
    }

    /**
     * Listado global de recargas para el panel de administración.
     *
     * @param array<string, mixed> $filtros
     * @return array{data: array<int, array<string, mixed>>, total: int}
     */
    public function adminList(array $filtros = []): array
    {
        $where = [];
        $parametros = [];

        if (isset($filtros['usuario_id'])) {
            $where[] = 'r.usuario_id = :usuario_id';
            $parametros[':usuario_id'] = (int) $filtros['usuario_id'];
        }

        if (isset($filtros['pareja_id'])) {
            $where[] = 'r.usuario_id IN (
                            SELECT usuario_1_id FROM parejas WHERE id = :pareja1
                            UNION
                            SELECT usuario_2_id FROM parejas WHERE id = :pareja2
                        )';
            $parametros[':pareja1'] = (int) $filtros['pareja_id'];
            $parametros[':pareja2'] = (int) $filtros['pareja_id'];
        }

        if (!empty($filtros['q'])) {
            $where[] = 'LOWER(COALESCE(r.motivo, \'\')) LIKE :q';
            $parametros[':q'] = '%' . strtolower(trim((string) $filtros['q'])) . '%';
        }

        if (isset($filtros['desde'])) {
            $where[] = 'r.fecha >= :desde';
            $parametros[':desde'] = (string) $filtros['desde'];
        }

        if (isset($filtros['hasta'])) {
            $where[] = 'r.fecha <= :hasta';
            $parametros[':hasta'] = (string) $filtros['hasta'];
        }

        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $sql = 'SELECT r.*, u.nombre AS usuario_nombre
                FROM recargas r
                INNER JOIN usuarios u ON u.id = r.usuario_id'
                . $whereSql
                . ' ORDER BY r.fecha DESC, r.id DESC
                    LIMIT :limite OFFSET :offset';

        $stmt = $this->db->prepare($sql);

        foreach ($parametros as $nombre => $valor) {
            $stmt->bindValue(
                $nombre,
                $valor,
                is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR
            );
        }

        $stmt->bindValue(':limite', (int) $filtros['limite'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int) $filtros['offset'], PDO::PARAM_INT);
        $stmt->execute();

        $recargas = array_map(
            static function (array $recarga): array {
                $recarga['id'] = (int) $recarga['id'];
                $recarga['usuario_id'] = (int) $recarga['usuario_id'];
                $recarga['importe'] = (int) $recarga['importe'];

                return $recarga;
            },
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM recargas r' . $whereSql);

        foreach ($parametros as $nombre => $valor) {
            $stmt->bindValue(
                $nombre,
                $valor,
                is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR
            );
        }

        $stmt->execute();

        return [
            'data' => $recargas,
            'total' => (int) $stmt->fetchColumn(),
        ];
    }

    /**
     * Crea una recarga para un miembro de la pareja.
     */
    public function create(int $usuarioId, int $importe, string $fecha, ?string $motivo): int
    {
        $sql = 'INSERT INTO recargas (usuario_id, importe, fecha, motivo)
                VALUES (:usuario_id, :importe, :fecha, :motivo)';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':importe', $importe, PDO::PARAM_INT);
        $stmt->bindValue(':fecha', $fecha, PDO::PARAM_STR);

        if ($motivo === null || $motivo === '') {
            $stmt->bindValue(':motivo', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':motivo', trim($motivo), PDO::PARAM_STR);
        }

        $stmt->execute();

        return (int) $this->db->lastInsertId();
    }

    /**
     * Modifica una recarga existente.
     */
    public function update(int $id, int $importe, string $fecha, ?string $motivo): bool
    {
        $sql = 'UPDATE recargas
                SET importe = :importe, fecha = :fecha, motivo = :motivo
                WHERE id = :id';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':importe', $importe, PDO::PARAM_INT);
        $stmt->bindValue(':fecha', $fecha, PDO::PARAM_STR);

        if ($motivo === null || $motivo === '') {
            $stmt->bindValue(':motivo', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':motivo', trim($motivo), PDO::PARAM_STR);
        }

        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Elimina una recarga.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM recargas WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }
}
