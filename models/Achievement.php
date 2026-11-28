<?php
declare(strict_types=1);

/**
 * Logros de una pareja calculados al vuelo.
 *
 * No se guardan en base de datos: se recalculan con los datos reales cada vez
 * que se consultan. Se usa la racha máxima para que un logro no vuelva a
 * bloquearse si la racha actual baja.
 */
class Achievement
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Devuelve los logros de la pareja con su estado de desbloqueo.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getLogros(int $parejaId): array
    {
        $rachaMaxima = $this->getRachaMaxima($parejaId);
        $cuidados = $this->getTotalCheckins($parejaId);
        $diasJuntos = $this->getDiasCompletos($parejaId);
        $coleccion = $this->getArticulosDistintos($parejaId);

        return [
            $this->logro(
                'first-care',
                'Primer cuidado',
                'Cuidar una planta por primera vez',
                '🌱',
                $cuidados,
                1
            ),
            $this->logro(
                'week',
                'Una semana creciendo',
                'Conseguir una racha de 7 días',
                '🌼',
                $rachaMaxima,
                7
            ),
            $this->logro(
                'together',
                'En equipo',
                'Cuidar el jardín juntos',
                '💕',
                $diasJuntos,
                1
            ),
            $this->logro(
                'month',
                'Un mes de constancia',
                'Alcanzar 30 días de racha',
                '🏵️',
                $rachaMaxima,
                30
            ),
            $this->logro(
                'collector',
                'Pequeños coleccionistas',
                'Comprar 3 elementos en la tienda',
                '🪴',
                $coleccion,
                3
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function logro(
        string $id,
        string $titulo,
        string $detalle,
        string $emoji,
        int $progreso,
        int $objetivo
    ): array {
        return [
            'id' => $id,
            'titulo' => $titulo,
            'detalle' => $detalle,
            'emoji' => $emoji,
            'desbloqueado' => $progreso >= $objetivo,
            'progreso' => min($progreso, $objetivo),
            'objetivo' => $objetivo,
        ];
    }

    private function getRachaMaxima(int $parejaId): int
    {
        $stmt = $this->db->prepare(
            'SELECT racha_maxima
             FROM pareja_streaks
             WHERE pareja_id = :pareja_id
             LIMIT 1'
        );
        $stmt->bindValue(':pareja_id', $parejaId, PDO::PARAM_INT);
        $stmt->execute();

        $valor = $stmt->fetchColumn();

        return $valor === false ? 0 : (int) $valor;
    }

    private function getTotalCheckins(int $parejaId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
             FROM pareja_streak_checkins
             WHERE pareja_id = :pareja_id'
        );
        $stmt->bindValue(':pareja_id', $parejaId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Días en los que los dos miembros entraron a cuidar el jardín.
     */
    private function getDiasCompletos(int $parejaId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
             FROM (
                 SELECT fecha
                 FROM pareja_streak_checkins
                 WHERE pareja_id = :pareja_id
                 GROUP BY fecha
                 HAVING COUNT(DISTINCT usuario_id) >= 2
             ) AS dias'
        );
        $stmt->bindValue(':pareja_id', $parejaId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Artículos distintos comprados en la tienda que no acabaron rechazados
     * ni cancelados.
     */
    private function getArticulosDistintos(int $parejaId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(DISTINCT item_id)
             FROM pareja_tareas
             WHERE pareja_id = :pareja_id
               AND estado IN ('pendiente', 'cumplida')"
        );
        $stmt->bindValue(':pareja_id', $parejaId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }
}
