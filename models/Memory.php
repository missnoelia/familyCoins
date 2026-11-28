<?php
declare(strict_types=1);

/**
 * Recuerdos compartidos de una pareja.
 *
 * Cada miembro puede guardar un recuerdo con un título, una fecha y una nota.
 * Los recuerdos son visibles para los dos miembros de la pareja.
 */
class Memory
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Lista los recuerdos de una pareja, del más reciente al más antiguo.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(int $parejaId): array
    {
        $sql = 'SELECT r.id, r.pareja_id, r.creado_por_id, r.titulo, r.fecha, r.nota,
                       r.created_at, u.nombre AS creado_por_nombre
                FROM pareja_recuerdos r
                INNER JOIN usuarios u ON u.id = r.creado_por_id
                WHERE r.pareja_id = :pareja_id
                ORDER BY r.fecha DESC, r.id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':pareja_id', $parejaId, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(
            static fn (array $recuerdo): array => self::normalizar($recuerdo),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    /**
     * Obtiene un recuerdo concreto comprobando que sea de la pareja.
     *
     * @return array<string, mixed>|null
     */
    public function getById(int $id, int $parejaId): ?array
    {
        $sql = 'SELECT r.id, r.pareja_id, r.creado_por_id, r.titulo, r.fecha, r.nota,
                       r.created_at, u.nombre AS creado_por_nombre
                FROM pareja_recuerdos r
                INNER JOIN usuarios u ON u.id = r.creado_por_id
                WHERE r.id = :id AND r.pareja_id = :pareja_id
                LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':pareja_id', $parejaId, PDO::PARAM_INT);
        $stmt->execute();

        $recuerdo = $stmt->fetch(PDO::FETCH_ASSOC);

        return $recuerdo === false ? null : self::normalizar($recuerdo);
    }

    /**
     * Guarda un recuerdo de la pareja y devuelve la fila creada.
     *
     * @return array<string, mixed>
     */
    public function create(
        int $parejaId,
        int $creadoPorId,
        string $titulo,
        string $fecha,
        string $nota
    ): array {
        $sql = 'INSERT INTO pareja_recuerdos
                    (pareja_id, creado_por_id, titulo, fecha, nota)
                VALUES
                    (:pareja_id, :creado_por_id, :titulo, :fecha, :nota)';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':pareja_id', $parejaId, PDO::PARAM_INT);
        $stmt->bindValue(':creado_por_id', $creadoPorId, PDO::PARAM_INT);
        $stmt->bindValue(':titulo', trim($titulo), PDO::PARAM_STR);
        $stmt->bindValue(':fecha', $fecha, PDO::PARAM_STR);
        $stmt->bindValue(':nota', trim($nota), PDO::PARAM_STR);
        $stmt->execute();

        $recuerdo = $this->getById((int) $this->db->lastInsertId(), $parejaId);

        if ($recuerdo === null) {
            throw new ApiException('No se ha podido recuperar el recuerdo creado.', 500);
        }

        return $recuerdo;
    }

    /**
     * @param array<string, mixed> $recuerdo
     * @return array<string, mixed>
     */
    private static function normalizar(array $recuerdo): array
    {
        foreach (['id', 'pareja_id', 'creado_por_id'] as $campo) {
            if (array_key_exists($campo, $recuerdo)) {
                $recuerdo[$campo] = (int) $recuerdo[$campo];
            }
        }

        return $recuerdo;
    }
}
