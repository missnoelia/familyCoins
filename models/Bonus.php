<?php
declare(strict_types=1);

/**
 * Bonos automáticos por cumpleaños y aniversario.
 *
 * Se procesan al iniciar sesión (check_login). La tabla pareja_bonos garantiza
 * que cada bono se entregue una sola vez por persona, tipo y año.
 */
class Bonus
{
    public const IMPORTE_CUMPLEANOS = 50;

    public const IMPORTE_ANIVERSARIO = 50;

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Entrega los bonos que correspondan hoy a la pareja del usuario.
     *
     * @return array<int, array<string, mixed>> Bonos creados en esta llamada.
     */
    public function procesar(int $usuarioId): array
    {
        $pareja = (new Couple($this->db))->getParejaDeUsuario($usuarioId);

        if ($pareja === null) {
            return [];
        }

        $hoy = api_hoy();
        $fecha = $hoy->format('Y-m-d');
        $anio = (int) $hoy->format('Y');
        $userModel = new User($this->db);
        $miembros = [];

        foreach ([$pareja['usuario_1_id'], $pareja['usuario_2_id']] as $miembroId) {
            $miembro = $userModel->getById($miembroId);

            if ($miembro !== null) {
                $miembros[$miembroId] = $miembro;
            }
        }

        if (count($miembros) !== 2) {
            return [];
        }

        $creados = [];

        $this->db->beginTransaction();

        try {
            foreach ($miembros as $miembroId => $miembro) {
                $nacimiento = $miembro['fecha_nacimiento'] ?? null;

                if (!is_string($nacimiento) || !$this->coincideFecha($nacimiento, $hoy)) {
                    continue;
                }

                $bono = $this->crearBono(
                    $pareja,
                    $miembroId,
                    'cumpleanos',
                    $anio,
                    self::IMPORTE_CUMPLEANOS,
                    'Cumpleaños de ' . mb_substr((string) $miembro['nombre'], 0, 80),
                    $fecha
                );

                if ($bono !== null) {
                    $bono['nombre'] = $miembro['nombre'];
                    $creados[] = $bono;
                }
            }

            $aniversario = $pareja['fecha_aniversario'];

            if (is_string($aniversario) && $this->esAniversarioVigente($aniversario, $hoy)) {
                foreach ($miembros as $miembroId => $miembro) {
                    $bono = $this->crearBono(
                        $pareja,
                        $miembroId,
                        'aniversario',
                        $anio,
                        self::IMPORTE_ANIVERSARIO,
                        'Aniversario de pareja',
                        $fecha
                    );

                    if ($bono !== null) {
                        $bono['nombre'] = $miembro['nombre'];
                        $creados[] = $bono;
                    }
                }
            }

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }

        return $creados;
    }

    /**
     * Crea la reserva idempotente del bono y su evento de suma.
     *
     * @param array{id: int, usuario_1_id: int, usuario_2_id: int, fecha_aniversario: ?string} $pareja
     * @return array<string, mixed>|null
     */
    private function crearBono(
        array $pareja,
        int $beneficiarioId,
        string $tipo,
        int $anio,
        int $importe,
        string $nombre,
        string $fecha
    ): ?array {
        // La fila se reserva antes de crear el evento. Si ya existía, no se
        // vuelve a entregar el bono.
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO pareja_bonos (pareja_id, usuario_id, tipo, anio, importe)
             VALUES (:pareja_id, :usuario_id, :tipo, :anio, :importe)'
        );
        $stmt->bindValue(':pareja_id', $pareja['id'], PDO::PARAM_INT);
        $stmt->bindValue(':usuario_id', $beneficiarioId, PDO::PARAM_INT);
        $stmt->bindValue(':tipo', $tipo, PDO::PARAM_STR);
        $stmt->bindValue(':anio', $anio, PDO::PARAM_INT);
        $stmt->bindValue(':importe', $importe, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            return null;
        }

        $bonoId = (int) $this->db->lastInsertId();
        $titularId = $pareja['usuario_1_id'] === $beneficiarioId
            ? $pareja['usuario_2_id']
            : $pareja['usuario_1_id'];

        // Un evento de suma acredita a la pareja del titular. Por eso el
        // titular es el otro miembro y el beneficiario quien lo registra.
        $eventoId = (new Event($this->db))->create(
            $titularId,
            $beneficiarioId,
            null,
            $nombre,
            $fecha,
            $importe,
            'suma',
            'bono'
        );

        $stmt = $this->db->prepare(
            'UPDATE pareja_bonos SET evento_id = :evento_id WHERE id = :id'
        );
        $stmt->bindValue(':evento_id', $eventoId, PDO::PARAM_INT);
        $stmt->bindValue(':id', $bonoId, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'id' => $bonoId,
            'pareja_id' => $pareja['id'],
            'usuario_id' => $beneficiarioId,
            'tipo' => $tipo,
            'anio' => $anio,
            'importe' => $importe,
            'evento_id' => $eventoId,
        ];
    }

    /**
     * El aniversario empieza a celebrarse a partir del año siguiente al inicio
     * de la relación. Así, vincularse hoy no genera un bono inmediato.
     */
    private function esAniversarioVigente(string $fecha, DateTimeImmutable $hoy): bool
    {
        $inicio = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);

        if ($inicio === false) {
            return false;
        }

        return (int) $hoy->format('Y') > (int) $inicio->format('Y')
            && $this->coincideFecha($fecha, $hoy);
    }

    /**
     * Compara el mes y el día de una fecha guardada con el día actual.
     *
     * Los cumpleaños del 29 de febrero se celebran el 28 en años no bisiestos.
     */
    private function coincideFecha(string $fecha, DateTimeImmutable $hoy): bool
    {
        $guardada = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);

        if ($guardada === false) {
            return false;
        }

        $mes = (int) $guardada->format('n');
        $dia = (int) $guardada->format('j');
        $mesHoy = (int) $hoy->format('n');
        $diaHoy = (int) $hoy->format('j');

        if ($mes === 2 && $dia === 29 && $mesHoy === 2 && $diaHoy === 28) {
            return $hoy->format('L') === '0';
        }

        return $mes === $mesHoy && $dia === $diaHoy;
    }
}
