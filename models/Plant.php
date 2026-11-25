<?php
declare(strict_types=1);

/**
 * Planta compartida de una pareja.
 *
 * La planta crece cuando la pareja completa un día de racha. Si la racha se
 * rompe pierde una etapa y, tras cuatro días sin renovarse, vuelve a semilla
 * conservando la mejor etapa alcanzada.
 */
class Plant
{
    public const COSTE_CAMBIO = 20;

    public const DIAS_REINICIO = 4;

    /** Días necesarios para pasar de cada etapa a la siguiente. */
    public const UMBRALES = [3, 4, 7, 16];

    public const CATALOGO = [
        'monstera' => [
            'nombre' => 'Monstera',
            'descripcion' => 'Hojas grandes y exuberantes para una pareja que crece junta.',
        ],
        'cactus' => [
            'nombre' => 'Cactus',
            'descripcion' => 'Fuerte y resistente, florece cuando menos lo esperas.',
        ],
        'helecho' => [
            'nombre' => 'Helecho',
            'descripcion' => 'Verde y tranquilo, prospera con cuidados constantes.',
        ],
        'girasol' => [
            'nombre' => 'Girasol',
            'descripcion' => 'Siempre mirando hacia la luz y hacia la otra persona.',
        ],
        'cerezo' => [
            'nombre' => 'Cerezo',
            'descripcion' => 'Paciente todo el año para regalar su mejor floración.',
        ],
        'suculenta' => [
            'nombre' => 'Suculenta',
            'descripcion' => 'Pequeña pero llena de vida, guarda agua para los días secos.',
        ],
    ];

    public const ETAPAS = [
        0 => ['id' => 'semilla', 'nombre' => 'Semilla'],
        1 => ['id' => 'brote', 'nombre' => 'Brote'],
        2 => ['id' => 'joven', 'nombre' => 'Joven'],
        3 => ['id' => 'fuerte', 'nombre' => 'Fuerte'],
        4 => ['id' => 'floracion', 'nombre' => 'Floración'],
    ];

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Catálogo fijo de plantas disponibles.
     *
     * @return array<int, array{id: string, nombre: string, descripcion: string}>
     */
    public static function getCatalogo(): array
    {
        $catalogo = [];

        foreach (self::CATALOGO as $id => $planta) {
            $catalogo[] = [
                'id' => $id,
                'nombre' => $planta['nombre'],
                'descripcion' => $planta['descripcion'],
            ];
        }

        return $catalogo;
    }

    /**
     * @return array<int, array{id: string, nombre: string}>
     */
    public static function getEtapas(): array
    {
        return array_values(self::ETAPAS);
    }

    /**
     * Estado completo de la planta de la pareja del usuario.
     *
     * @return array<string, mixed>
     */
    public function getEstado(int $usuarioId): array
    {
        $pareja = $this->getPareja($usuarioId);
        $this->sincronizar($pareja['id']);

        $fila = $this->getFila($pareja['id']);

        return [
            'pareja_id' => $pareja['id'],
            'fecha' => api_hoy()->format('Y-m-d'),
            'catalogo' => self::getCatalogo(),
            'etapas' => self::getEtapas(),
            'umbrales' => self::UMBRALES,
            'coste_cambio' => self::COSTE_CAMBIO,
            'planta' => $fila === null ? null : $this->formatear($fila),
        ];
    }

    /**
     * Elige la primera planta (gratis) o cambia la actual (20 coins).
     *
     * El cambio conserva la etapa y el progreso alcanzados.
     *
     * @return array{estado: array<string, mixed>, coste: int}
     */
    public function elegir(int $usuarioId, string $plantaId): array
    {
        $plantaId = strtolower(trim($plantaId));

        if (!isset(self::CATALOGO[$plantaId])) {
            throw new ApiException('La planta indicada no existe.', 400);
        }

        $pareja = $this->getPareja($usuarioId);
        $parejaId = $pareja['id'];
        $coste = 0;
        $hoy = api_hoy()->format('Y-m-d');
        $eventModel = new Event($this->db);

        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare('SELECT id FROM parejas WHERE id = :id FOR UPDATE');
            $stmt->bindValue(':id', $parejaId, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->fetchColumn() === false) {
                throw new ApiException('La pareja ya no existe.', 409);
            }

            $fila = $this->getFila($parejaId, true);

            if ($fila === null) {
                $stmt = $this->db->prepare(
                    'INSERT INTO pareja_plantas
                        (pareja_id, planta_id, etapa, etapa_maxima, progreso_dias,
                         elegida_por_id, elegida_en, updated_at)
                     VALUES
                        (:pareja_id, :planta_id, 0, 0, 0, :usuario_id, NOW(), NOW())'
                );
                $stmt->bindValue(':pareja_id', $parejaId, PDO::PARAM_INT);
                $stmt->bindValue(':planta_id', $plantaId, PDO::PARAM_STR);
                $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
                $stmt->execute();
            } elseif ($fila['planta_id'] === $plantaId) {
                // Elegir la misma planta no tiene coste ni cambia el progreso.
            } else {
                $saldo = (new Balance($this->db))->getSaldoUsuario($usuarioId);

                if ($saldo < self::COSTE_CAMBIO) {
                    throw new ApiException(
                        'No tienes coins suficientes para cambiar de planta ('
                        . self::COSTE_CAMBIO . ').',
                        409
                    );
                }

                $eventoId = $eventModel->create(
                    $usuarioId,
                    $usuarioId,
                    null,
                    'Cambio de planta: ' . self::CATALOGO[$plantaId]['nombre'],
                    $hoy,
                    self::COSTE_CAMBIO,
                    'resta',
                    'planta'
                );

                $stmt = $this->db->prepare(
                    'UPDATE pareja_plantas
                     SET planta_id = :planta_id,
                         elegida_por_id = :usuario_id,
                         elegida_en = NOW(),
                         updated_at = NOW()
                     WHERE pareja_id = :pareja_id'
                );
                $stmt->bindValue(':planta_id', $plantaId, PDO::PARAM_STR);
                $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
                $stmt->bindValue(':pareja_id', $parejaId, PDO::PARAM_INT);
                $stmt->execute();

                $stmt = $this->db->prepare(
                    'INSERT INTO pareja_planta_cambios
                        (pareja_id, usuario_id, planta_anterior, planta_nueva, coste, evento_id)
                     VALUES
                        (:pareja_id, :usuario_id, :anterior, :nueva, :coste, :evento_id)'
                );
                $stmt->bindValue(':pareja_id', $parejaId, PDO::PARAM_INT);
                $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
                $stmt->bindValue(':anterior', $fila['planta_id'], PDO::PARAM_STR);
                $stmt->bindValue(':nueva', $plantaId, PDO::PARAM_STR);
                $stmt->bindValue(':coste', self::COSTE_CAMBIO, PDO::PARAM_INT);
                $stmt->bindValue(':evento_id', $eventoId, PDO::PARAM_INT);
                $stmt->execute();

                $coste = self::COSTE_CAMBIO;
            }

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }

        return [
            'estado' => $this->getEstado($usuarioId),
            'coste' => $coste,
        ];
    }

    /**
     * Aplica rotura o reinicio según los días transcurridos desde el último día
     * completo de la racha. Es idempotente para el mismo último día.
     */
    public function sincronizar(int $parejaId): void
    {
        $propia = !$this->db->inTransaction();

        if ($propia) {
            $this->db->beginTransaction();
        }

        try {
            $fila = $this->getFila($parejaId, true);

            if ($fila === null) {
                if ($propia) {
                    $this->db->commit();
                }

                return;
            }

            $streak = $this->getFilaStreak($parejaId);
            $ultimo = $streak['ultimo_dia_completo'] ?? null;

            if ($ultimo === null) {
                if ($propia) {
                    $this->db->commit();
                }

                return;
            }

            $dias = $this->diferenciaDias($ultimo, api_hoy()->format('Y-m-d'));

            if ($dias >= self::DIAS_REINICIO && $fila['reiniciada_para'] !== $ultimo) {
                $stmt = $this->db->prepare(
                    'UPDATE pareja_plantas
                     SET etapa = 0,
                         progreso_dias = 0,
                         reiniciada_para = :ultimo,
                         updated_at = NOW()
                     WHERE pareja_id = :pareja_id'
                );
                $stmt->bindValue(':ultimo', $ultimo, PDO::PARAM_STR);
                $stmt->bindValue(':pareja_id', $parejaId, PDO::PARAM_INT);
                $stmt->execute();
            } elseif ($dias >= 2
                && $fila['degradada_para'] !== $ultimo
                && $fila['reiniciada_para'] !== $ultimo
            ) {
                $stmt = $this->db->prepare(
                    'UPDATE pareja_plantas
                     SET etapa = GREATEST(0, etapa - 1),
                         progreso_dias = 0,
                         degradada_para = :ultimo,
                         updated_at = NOW()
                     WHERE pareja_id = :pareja_id'
                );
                $stmt->bindValue(':ultimo', $ultimo, PDO::PARAM_STR);
                $stmt->bindValue(':pareja_id', $parejaId, PDO::PARAM_INT);
                $stmt->execute();
            }

            if ($propia) {
                $this->db->commit();
            }
        } catch (Throwable $e) {
            if ($propia && $this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Suma un día de crecimiento por un día de racha completado.
     *
     * Debe llamarse dentro de la transacción de Streak::registrarEntrada().
     */
    public function registrarDiaCompletado(int $parejaId): void
    {
        $propia = !$this->db->inTransaction();

        if ($propia) {
            $this->db->beginTransaction();
        }

        try {
            $fila = $this->getFila($parejaId, true);

            if ($fila === null) {
                if ($propia) {
                    $this->db->commit();
                }

                return;
            }

            $etapa = (int) $fila['etapa'];
            $progreso = (int) $fila['progreso_dias'] + 1;
            $maxima = (int) $fila['etapa_maxima'];

            while ($etapa < 4 && $progreso >= self::UMBRALES[$etapa]) {
                $progreso -= self::UMBRALES[$etapa];
                $etapa++;
            }

            if ($etapa >= 4) {
                $etapa = 4;
                $progreso = 0;
            }

            $maxima = max($maxima, $etapa);

            $stmt = $this->db->prepare(
                'UPDATE pareja_plantas
                 SET etapa = :etapa,
                     etapa_maxima = :etapa_maxima,
                     progreso_dias = :progreso,
                     updated_at = NOW()
                 WHERE pareja_id = :pareja_id'
            );
            $stmt->bindValue(':etapa', $etapa, PDO::PARAM_INT);
            $stmt->bindValue(':etapa_maxima', $maxima, PDO::PARAM_INT);
            $stmt->bindValue(':progreso', $progreso, PDO::PARAM_INT);
            $stmt->bindValue(':pareja_id', $parejaId, PDO::PARAM_INT);
            $stmt->execute();

            if ($propia) {
                $this->db->commit();
            }
        } catch (Throwable $e) {
            if ($propia && $this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    /**
     * @return array{id: int, usuario_1_id: int, usuario_2_id: int}
     */
    private function getPareja(int $usuarioId): array
    {
        $pareja = (new Couple($this->db))->getParejaDeUsuario($usuarioId);

        if ($pareja === null) {
            throw new ApiException('Debes vincular tu pareja antes de cuidar la planta.', 403);
        }

        return [
            'id' => $pareja['id'],
            'usuario_1_id' => $pareja['usuario_1_id'],
            'usuario_2_id' => $pareja['usuario_2_id'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getFila(int $parejaId, bool $bloquear = false): ?array
    {
        $sql = 'SELECT pareja_id, planta_id, etapa, etapa_maxima, progreso_dias,
                       degradada_para, reiniciada_para, elegida_por_id, elegida_en, updated_at
                FROM pareja_plantas
                WHERE pareja_id = :pareja_id
                LIMIT 1';

        if ($bloquear) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':pareja_id', $parejaId, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($fila === false) {
            return null;
        }

        return [
            'pareja_id' => (int) $fila['pareja_id'],
            'planta_id' => (string) $fila['planta_id'],
            'etapa' => (int) $fila['etapa'],
            'etapa_maxima' => (int) $fila['etapa_maxima'],
            'progreso_dias' => (int) $fila['progreso_dias'],
            'degradada_para' => $fila['degradada_para'],
            'reiniciada_para' => $fila['reiniciada_para'],
            'elegida_por_id' => (int) $fila['elegida_por_id'],
            'elegida_en' => $fila['elegida_en'],
            'updated_at' => $fila['updated_at'],
        ];
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
     * @param array<string, mixed> $fila
     * @return array<string, mixed>
     */
    private function formatear(array $fila): array
    {
        $etapa = (int) $fila['etapa'];
        $etapa = max(0, min(4, $etapa));
        $umbral = $etapa < 4 ? self::UMBRALES[$etapa] : null;
        $progreso = (int) $fila['progreso_dias'];
        $catalogo = self::CATALOGO[$fila['planta_id']] ?? [
            'nombre' => $fila['planta_id'],
            'descripcion' => null,
        ];

        return [
            'planta_id' => $fila['planta_id'],
            'nombre' => $catalogo['nombre'],
            'descripcion' => $catalogo['descripcion'],
            'etapa' => $etapa,
            'etapa_id' => self::ETAPAS[$etapa]['id'],
            'etapa_nombre' => self::ETAPAS[$etapa]['nombre'],
            'etapa_maxima' => (int) $fila['etapa_maxima'],
            'etapa_maxima_id' => self::ETAPAS[(int) $fila['etapa_maxima']]['id'],
            'progreso_dias' => $progreso,
            'dias_para_siguiente' => $umbral === null ? null : max(0, $umbral - $progreso),
            'progreso_etapa' => $umbral === null ? 100 : (int) floor(min(100, ($progreso / $umbral) * 100)),
            'completada' => $etapa >= 4,
            'elegida_por_id' => (int) $fila['elegida_por_id'],
            'elegida_en' => $fila['elegida_en'],
            'updated_at' => $fila['updated_at'],
        ];
    }

    private function diferenciaDias(string $desde, string $hasta): int
    {
        $inicio = DateTimeImmutable::createFromFormat('!Y-m-d', $desde);
        $fin = DateTimeImmutable::createFromFormat('!Y-m-d', $hasta);

        if ($inicio === false || $fin === false) {
            return 0;
        }

        return (int) $inicio->diff($fin)->format('%a');
    }
}
