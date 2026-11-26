<?php
declare(strict_types=1);

/**
 * Racha compartida de una pareja.
 *
 * Cada miembro registra su entrada diaria. Cuando los dos han entrado el mismo
 * día, el día se considera completo y la racha avanza una sola vez.
 */
class Streak
{
    private const ZONA_POR_DEFECTO = 'Europe/Madrid';

    private PDO $db;
    private DateTimeZone $zona;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $nombreZona = getenv('STREAK_TIMEZONE');

        if ($nombreZona === false || trim($nombreZona) === '') {
            $nombreZona = self::ZONA_POR_DEFECTO;
        }

        try {
            $this->zona = new DateTimeZone($nombreZona);
        } catch (Throwable $e) {
            $this->zona = new DateTimeZone(self::ZONA_POR_DEFECTO);
        }
    }

    /**
     * Devuelve la pareja del usuario.
     *
     * @return array{id: int, usuario_1_id: int, usuario_2_id: int}
     */
    public function getPareja(int $usuarioId): array
    {
        $sql = 'SELECT id, usuario_1_id, usuario_2_id
                FROM parejas
                WHERE usuario_1_id = :usuario1 OR usuario_2_id = :usuario2
                LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':usuario1', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':usuario2', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        $pareja = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($pareja === false) {
            throw new ApiException('Debes vincular tu pareja antes de consultar la racha.', 403);
        }

        return [
            'id' => (int) $pareja['id'],
            'usuario_1_id' => (int) $pareja['usuario_1_id'],
            'usuario_2_id' => (int) $pareja['usuario_2_id'],
        ];
    }

    /**
     * Estado actual de la racha para el usuario autenticado.
     *
     * @return array<string, mixed>
     */
    public function getEstado(int $usuarioId): array
    {
        $pareja = $this->getPareja($usuarioId);
        $parejaId = $pareja['id'];
        $hoy = $this->hoy();
        $ayer = $this->ayer();

        // Aplica una posible rotura o reinicio de la planta al consultar.
        (new Plant($this->db))->sincronizar($parejaId);

        $fila = $this->getFilaStreak($parejaId);
        $entradas = $this->getEntradas($parejaId, $hoy);

        $miembro1 = $pareja['usuario_1_id'];
        $miembro2 = $pareja['usuario_2_id'];
        $otroMiembro = $usuarioId === $miembro1 ? $miembro2 : $miembro1;

        $completado = in_array($miembro1, $entradas, true)
            && in_array($miembro2, $entradas, true);

        $ultimo = $fila['ultimo_dia_completo'];
        $racha = (int) $fila['racha_actual'];

        // Si el último día completo no es hoy ni ayer, la racha ya está rota.
        if ($ultimo === null || ($ultimo !== $hoy && $ultimo !== $ayer)) {
            $racha = 0;
        }

        return [
            'pareja_id' => $parejaId,
            'racha_actual' => $racha,
            'racha_maxima' => (int) $fila['racha_maxima'],
            'ultimo_dia_completo' => $ultimo,
            'fecha' => $hoy,
            'completado_hoy' => $completado,
            'yo_he_entrado' => in_array($usuarioId, $entradas, true),
            'pareja_ha_entrado' => in_array($otroMiembro, $entradas, true),
        ];
    }

    /**
     * Registra la entrada de hoy del usuario y avanza la racha si procede.
     *
     * Es idempotente: repetir la entrada el mismo día no duplica nada. La fila
     * de la pareja se bloquea para que dos peticiones simultáneas no sumen dos
     * veces el mismo día.
     *
     * @return array<string, mixed>
     */
    public function registrarEntrada(int $usuarioId): array
    {
        $pareja = $this->getPareja($usuarioId);
        $parejaId = $pareja['id'];
        $hoy = $this->hoy();
        $ayer = $this->ayer();

        $registrada = false;
        $completadoAhora = false;

        $this->db->beginTransaction();

        try {
            // Serializa las entradas de la misma pareja.
            $stmt = $this->db->prepare('SELECT id FROM parejas WHERE id = :id FOR UPDATE');
            $stmt->bindValue(':id', $parejaId, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->fetchColumn() === false) {
                throw new ApiException('La pareja ya no existe.', 409);
            }

            $stmt = $this->db->prepare(
                'INSERT IGNORE INTO pareja_streaks (pareja_id)
                 VALUES (:pareja_id)'
            );
            $stmt->bindValue(':pareja_id', $parejaId, PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $this->db->prepare(
                'INSERT IGNORE INTO pareja_streak_checkins (pareja_id, usuario_id, fecha)
                 VALUES (:pareja_id, :usuario_id, :fecha)'
            );
            $stmt->bindValue(':pareja_id', $parejaId, PDO::PARAM_INT);
            $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
            $stmt->bindValue(':fecha', $hoy, PDO::PARAM_STR);
            $stmt->execute();

            $registrada = $stmt->rowCount() > 0;

            $fila = $this->getFilaStreak($parejaId);
            $entradas = $this->getEntradas($parejaId, $hoy);

            $completado = in_array($pareja['usuario_1_id'], $entradas, true)
                && in_array($pareja['usuario_2_id'], $entradas, true);

            $ultimo = $fila['ultimo_dia_completo'];

            if ($completado && $ultimo !== $hoy) {
                $plantModel = new Plant($this->db);

                // La planta se sincroniza con el último día anterior antes de
                // actualizar la racha, para aplicar rotura o reinicio una sola vez.
                $plantModel->sincronizar($parejaId);

                $racha = $ultimo === $ayer ? (int) $fila['racha_actual'] + 1 : 1;
                $maxima = max((int) $fila['racha_maxima'], $racha);

                $stmt = $this->db->prepare(
                    'UPDATE pareja_streaks
                     SET racha_actual = :racha_actual,
                         racha_maxima = :racha_maxima,
                         ultimo_dia_completo = :ultimo_dia,
                         updated_at = NOW()
                     WHERE pareja_id = :pareja_id'
                );
                $stmt->bindValue(':racha_actual', $racha, PDO::PARAM_INT);
                $stmt->bindValue(':racha_maxima', $maxima, PDO::PARAM_INT);
                $stmt->bindValue(':ultimo_dia', $hoy, PDO::PARAM_STR);
                $stmt->bindValue(':pareja_id', $parejaId, PDO::PARAM_INT);
                $stmt->execute();

                $plantModel->registrarDiaCompletado($parejaId);

                $completadoAhora = true;
            }

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }

        $estado = $this->getEstado($usuarioId);
        $estado['registrada'] = $registrada;
        $estado['completado_ahora'] = $completadoAhora;

        return $estado;
    }

    /**
     * @return array{racha_actual: int, racha_maxima: int, ultimo_dia_completo: ?string}
     */
    private function getFilaStreak(int $parejaId): array
    {
        $stmt = $this->db->prepare(
            'SELECT racha_actual, racha_maxima, ultimo_dia_completo
             FROM pareja_streaks
             WHERE pareja_id = :pareja_id
             LIMIT 1'
        );
        $stmt->bindValue(':pareja_id', $parejaId, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($fila === false) {
            return [
                'racha_actual' => 0,
                'racha_maxima' => 0,
                'ultimo_dia_completo' => null,
            ];
        }

        return [
            'racha_actual' => (int) $fila['racha_actual'],
            'racha_maxima' => (int) $fila['racha_maxima'],
            'ultimo_dia_completo' => $fila['ultimo_dia_completo'] === null
                ? null
                : (string) $fila['ultimo_dia_completo'],
        ];
    }

    /**
     * @return array<int, int>
     */
    private function getEntradas(int $parejaId, string $fecha): array
    {
        $stmt = $this->db->prepare(
            'SELECT usuario_id
             FROM pareja_streak_checkins
             WHERE pareja_id = :pareja_id AND fecha = :fecha'
        );
        $stmt->bindValue(':pareja_id', $parejaId, PDO::PARAM_INT);
        $stmt->bindValue(':fecha', $fecha, PDO::PARAM_STR);
        $stmt->execute();

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function hoy(): string
    {
        return (new DateTimeImmutable('now', $this->zona))->format('Y-m-d');
    }

    private function ayer(): string
    {
        return (new DateTimeImmutable('now', $this->zona))
            ->modify('-1 day')
            ->format('Y-m-d');
    }
}
