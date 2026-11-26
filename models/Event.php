<?php
declare(strict_types=1);

class Event
{
    public const ORIGENES = ['manual', 'tienda', 'tarea', 'bono', 'planta'];

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Comprueba que el otro usuario sea el propio usuario o su pareja.
     */
    public function pertenecePareja(int $usuarioId, int $otroUsuarioId): bool
    {
        return (new Couple($this->db))->esDeLaPareja($usuarioId, $otroUsuarioId);
    }

    /**
     * Consulta los eventos de la pareja aplicando filtros y ordenación.
     *
     * Filtros admitidos: usuario_id, efecto, desde, hasta, nombre, anulado,
     * orden y dir.
     *
     * @param array<string, mixed> $filtros
     * @return array<int, array<string, mixed>>
     */
    public function getAll(int $usuarioId, array $filtros = []): array
    {
        $sql = 'SELECT e.*,
                       u.nombre AS usuario_nombre,
                       r.nombre AS registrado_por_nombre
                FROM eventos e
                INNER JOIN usuarios u ON u.id = e.usuario_id
                INNER JOIN usuarios r ON r.id = e.registrado_por_id
                WHERE (
                    e.usuario_id = :usuario1
                    OR e.usuario_id = (
                        SELECT CASE
                            WHEN p.usuario_1_id = :usuario2 THEN p.usuario_2_id
                            ELSE p.usuario_1_id
                        END
                        FROM parejas p
                        WHERE p.usuario_1_id = :usuario3 OR p.usuario_2_id = :usuario4
                        LIMIT 1
                    )
                )';

        $parametros = [
            ':usuario1' => $usuarioId,
            ':usuario2' => $usuarioId,
            ':usuario3' => $usuarioId,
            ':usuario4' => $usuarioId,
        ];

        if (isset($filtros['usuario_id'])) {
            $sql .= ' AND e.usuario_id = :filtro_usuario';
            $parametros[':filtro_usuario'] = (int) $filtros['usuario_id'];
        }

        if (isset($filtros['efecto'])) {
            $sql .= ' AND e.efecto = :filtro_efecto';
            $parametros[':filtro_efecto'] = (string) $filtros['efecto'];
        }

        if (isset($filtros['desde'])) {
            $sql .= ' AND e.fecha >= :filtro_desde';
            $parametros[':filtro_desde'] = (string) $filtros['desde'];
        }

        if (isset($filtros['hasta'])) {
            $sql .= ' AND e.fecha <= :filtro_hasta';
            $parametros[':filtro_hasta'] = (string) $filtros['hasta'];
        }

        if (isset($filtros['nombre']) && $filtros['nombre'] !== '') {
            $sql .= ' AND LOWER(e.nombre) LIKE LOWER(:filtro_nombre)';
            $parametros[':filtro_nombre'] = '%' . $filtros['nombre'] . '%';
        }

        if (isset($filtros['anulado'])) {
            // El valor ya viene validado como bool, así que se puede usar
            // directamente como literal.
            $sql .= ' AND e.anulado = ' . ($filtros['anulado'] ? 'TRUE' : 'FALSE');
        }

        $ordenes = [
            'fecha' => 'e.fecha',
            'coste' => 'e.coste',
            'persona' => 'e.usuario_id',
            'usuario' => 'e.usuario_id',
            'nombre' => 'e.nombre',
            'id' => 'e.id',
        ];

        $ordenSolicitado = strtolower((string) ($filtros['orden'] ?? 'fecha'));
        $orden = $ordenes[$ordenSolicitado] ?? 'e.fecha';
        $direccion = strtolower((string) ($filtros['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

        $sql .= ' ORDER BY ' . $orden . ' ' . $direccion . ', e.id DESC';

        $stmt = $this->db->prepare($sql);

        foreach ($parametros as $nombre => $valor) {
            $stmt->bindValue(
                $nombre,
                $valor,
                is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR
            );
        }

        $stmt->execute();

        $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static fn (array $evento): array => self::normalizarEvento($evento), $eventos);
    }

    /**
     * Listado global de eventos para el panel de administración.
     *
     * @param array<string, mixed> $filtros
     * @return array{data: array<int, array<string, mixed>>, total: int}
     */
    public function adminList(array $filtros = []): array
    {
        $where = [];
        $parametros = [];

        if (isset($filtros['usuario_id'])) {
            $where[] = 'e.usuario_id = :usuario_id';
            $parametros[':usuario_id'] = (int) $filtros['usuario_id'];
        }

        if (isset($filtros['pareja_id'])) {
            $where[] = 'e.usuario_id IN (
                            SELECT usuario_1_id FROM parejas WHERE id = :pareja1
                            UNION
                            SELECT usuario_2_id FROM parejas WHERE id = :pareja2
                        )';
            $parametros[':pareja1'] = (int) $filtros['pareja_id'];
            $parametros[':pareja2'] = (int) $filtros['pareja_id'];
        }

        if (!empty($filtros['q'])) {
            $where[] = 'LOWER(e.nombre) LIKE :q';
            $parametros[':q'] = '%' . strtolower(trim((string) $filtros['q'])) . '%';
        }

        if (!empty($filtros['efecto'])) {
            $where[] = 'e.efecto = :efecto';
            $parametros[':efecto'] = (string) $filtros['efecto'];
        }

        if (isset($filtros['desde'])) {
            $where[] = 'e.fecha >= :desde';
            $parametros[':desde'] = (string) $filtros['desde'];
        }

        if (isset($filtros['hasta'])) {
            $where[] = 'e.fecha <= :hasta';
            $parametros[':hasta'] = (string) $filtros['hasta'];
        }

        if (array_key_exists('anulado', $filtros) && is_bool($filtros['anulado'])) {
            $where[] = 'e.anulado = ' . ($filtros['anulado'] ? 'TRUE' : 'FALSE');
        }

        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $sql = 'SELECT e.*,
                       u.nombre AS usuario_nombre,
                       r.nombre AS registrado_por_nombre
                FROM eventos e
                INNER JOIN usuarios u ON u.id = e.usuario_id
                INNER JOIN usuarios r ON r.id = e.registrado_por_id'
                . $whereSql
                . ' ORDER BY e.fecha DESC, e.id DESC
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

        $eventos = array_map(
            static fn (array $evento): array => self::normalizarEvento($evento),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM eventos e' . $whereSql);

        foreach ($parametros as $nombre => $valor) {
            $stmt->bindValue(
                $nombre,
                $valor,
                is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR
            );
        }

        $stmt->execute();

        return [
            'data' => $eventos,
            'total' => (int) $stmt->fetchColumn(),
        ];
    }

    /**
     * Obtiene un evento por su ID, incluidos los anulados.
     *
     * @return array<string, mixed>|null
     */
    public function getById(int $id): ?array
    {
        $sql = 'SELECT e.*,
                       u.nombre AS usuario_nombre,
                       r.nombre AS registrado_por_nombre
                FROM eventos e
                INNER JOIN usuarios u ON u.id = e.usuario_id
                INNER JOIN usuarios r ON r.id = e.registrado_por_id
                WHERE e.id = :id
                LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $evento = $stmt->fetch(PDO::FETCH_ASSOC);

        return $evento ? self::normalizarEvento($evento) : null;
    }

    /**
     * Normaliza los tipos que PostgreSQL puede devolver como texto.
     *
     * @param array<string, mixed> $evento
     * @return array<string, mixed>
     */
    private static function normalizarEvento(array $evento): array
    {
        foreach (['id', 'usuario_id', 'registrado_por_id', 'predefinido_id', 'coste'] as $campo) {
            if (array_key_exists($campo, $evento)) {
                $evento[$campo] = $evento[$campo] === null ? null : (int) $evento[$campo];
            }
        }

        if (array_key_exists('anulado', $evento)) {
            $evento['anulado'] = self::esAnulado($evento['anulado']);
        }

        if (array_key_exists('origen', $evento)) {
            $evento['origen'] = (string) $evento['origen'];
        }

        return $evento;
    }

    /**
     * Indica si un evento lo generó el sistema (tienda, tarea, bono o planta)
     * y, por tanto, no debe editarse ni anularse desde el historial manual.
     */
    public static function esSistema(mixed $origen): bool
    {
        return is_string($origen) && $origen !== '' && $origen !== 'manual';
    }

    /**
     * Crea un evento. El destinatario es quien recibe el efecto.
     */
    public function create(
        int $usuarioId,
        int $registradoPorId,
        ?int $predefinidoId,
        string $nombre,
        string $fecha,
        int $coste,
        string $efecto,
        string $origen = 'manual'
    ): int {
        if (!in_array($origen, self::ORIGENES, true)) {
            throw new ApiException('El origen del evento no es válido.', 400);
        }

        $sql = 'INSERT INTO eventos
                    (usuario_id, registrado_por_id, predefinido_id, nombre, fecha, coste, efecto, origen)
                VALUES
                    (:usuario_id, :registrado_por_id, :predefinido_id, :nombre, :fecha, :coste, :efecto, :origen)';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':registrado_por_id', $registradoPorId, PDO::PARAM_INT);

        if ($predefinidoId === null) {
            $stmt->bindValue(':predefinido_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':predefinido_id', $predefinidoId, PDO::PARAM_INT);
        }

        $stmt->bindValue(':nombre', trim($nombre), PDO::PARAM_STR);
        $stmt->bindValue(':fecha', $fecha, PDO::PARAM_STR);
        $stmt->bindValue(':coste', $coste, PDO::PARAM_INT);
        $stmt->bindValue(':efecto', $efecto, PDO::PARAM_STR);
        $stmt->bindValue(':origen', $origen, PDO::PARAM_STR);
        $stmt->execute();

        return (int) $this->db->lastInsertId();
    }

    /**
     * Modifica un evento que no esté anulado.
     */
    public function update(
        int $id,
        int $usuarioId,
        ?int $predefinidoId,
        string $nombre,
        string $fecha,
        int $coste,
        string $efecto
    ): bool {
        $sql = 'UPDATE eventos
                SET usuario_id = :usuario_id,
                    predefinido_id = :predefinido_id,
                    nombre = :nombre,
                    fecha = :fecha,
                    coste = :coste,
                    efecto = :efecto
                WHERE id = :id AND anulado = FALSE';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);

        if ($predefinidoId === null) {
            $stmt->bindValue(':predefinido_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':predefinido_id', $predefinidoId, PDO::PARAM_INT);
        }

        $stmt->bindValue(':nombre', trim($nombre), PDO::PARAM_STR);
        $stmt->bindValue(':fecha', $fecha, PDO::PARAM_STR);
        $stmt->bindValue(':coste', $coste, PDO::PARAM_INT);
        $stmt->bindValue(':efecto', $efecto, PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Anula un evento sin eliminarlo del historial.
     */
    public function anular(int $id): bool
    {
        $sql = 'UPDATE eventos
                SET anulado = TRUE
                WHERE id = :id AND anulado = FALSE';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Lógica única del efecto resta/suma.
     *
     * - Un evento de tipo resta descuenta el coste al usuario titular.
     * - Un evento de tipo suma acredita el coste a la pareja del titular.
     * - Los eventos anulados no afectan al saldo.
     *
     * El evento debe pertenecer al usuario o a su pareja.
     *
     * @param array<string, mixed> $evento
     */
    public function calcularImpacto(int $usuarioId, array $evento): int
    {
        if (self::esAnulado($evento['anulado'] ?? false)) {
            return 0;
        }

        $titular = (int) $evento['usuario_id'];
        $coste = (int) $evento['coste'];
        $efecto = strtolower((string) $evento['efecto']);

        if ($efecto === 'resta' && $titular === $usuarioId) {
            return -$coste;
        }

        if ($efecto === 'suma' && $titular !== $usuarioId) {
            return $coste;
        }

        return 0;
    }

    /**
     * Normaliza el campo anulado de PostgreSQL.
     */
    public static function esAnulado(mixed $valor): bool
    {
        return $valor === true
            || $valor === 1
            || $valor === '1'
            || $valor === 't'
            || $valor === 'true';
    }
}
