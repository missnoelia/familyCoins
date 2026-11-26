<?php
declare(strict_types=1);

/**
 * Tienda compartida de la pareja y tareas asociadas.
 *
 * Los artículos globales están disponibles para todas las parejas. Cada pareja
 * puede crear hasta 15 artículos propios. Las compras de recompensas y permisos
 * descuentan coins al solicitante; las de tipo ganancia solo suman coins cuando
 * la pareja confirma la tarea.
 */
class Shop
{
    public const TIPOS = ['recompensa', 'permiso', 'ganancia'];

    public const MAX_PERSONALIZADOS = 15;

    public const COSTE_MAXIMO = 100;

    public const ESTADOS_TAREA = ['pendiente', 'cumplida', 'rechazada', 'cancelada'];

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Catálogo visible para la pareja: artículos globales y propios.
     *
     * @return array<string, mixed>
     */
    public function getTienda(int $usuarioId): array
    {
        $pareja = $this->getPareja($usuarioId);

        $stmt = $this->db->prepare(
            'SELECT i.*, u.nombre AS creado_por_nombre,
                    CASE WHEN i.pareja_id IS NULL THEN \'global\' ELSE \'pareja\' END AS alcance
             FROM tienda_items i
             LEFT JOIN usuarios u ON u.id = i.creado_por_id
             WHERE i.activo = TRUE
               AND (i.pareja_id IS NULL OR i.pareja_id = :pareja_id)
             ORDER BY (i.pareja_id IS NULL) DESC, i.tipo ASC, i.coste ASC, i.id ASC'
        );
        $stmt->bindValue(':pareja_id', $pareja['id'], PDO::PARAM_INT);
        $stmt->execute();

        $items = array_map(
            static fn (array $item): array => self::normalizarItem($item),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );

        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
             FROM tienda_items
             WHERE pareja_id = :pareja_id AND activo = TRUE'
        );
        $stmt->bindValue(':pareja_id', $pareja['id'], PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $items,
            'personalizados' => (int) $stmt->fetchColumn(),
            'maximo_personalizados' => self::MAX_PERSONALIZADOS,
            'tipos' => self::TIPOS,
            'coste_maximo' => self::COSTE_MAXIMO,
        ];
    }

    /**
     * Obtiene un artículo visible para la pareja.
     *
     * @return array<string, mixed>|null
     */
    public function getItem(int $itemId, int $parejaId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT i.*, u.nombre AS creado_por_nombre,
                    CASE WHEN i.pareja_id IS NULL THEN \'global\' ELSE \'pareja\' END AS alcance
             FROM tienda_items i
             LEFT JOIN usuarios u ON u.id = i.creado_por_id
             WHERE i.id = :id
               AND i.activo = TRUE
               AND (i.pareja_id IS NULL OR i.pareja_id = :pareja_id)
             LIMIT 1'
        );
        $stmt->bindValue(':id', $itemId, PDO::PARAM_INT);
        $stmt->bindValue(':pareja_id', $parejaId, PDO::PARAM_INT);
        $stmt->execute();

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item === false ? null : self::normalizarItem($item);
    }

    /**
     * Crea un artículo personalizado de la pareja.
     *
     * @return array<string, mixed>
     */
    public function crearItem(
        int $usuarioId,
        string $tipo,
        string $nombre,
        ?string $descripcion,
        int $coste
    ): array {
        $pareja = $this->getPareja($usuarioId);
        [$tipo, $nombre, $descripcion, $coste] = $this->validarItem(
            $tipo,
            $nombre,
            $descripcion,
            $coste
        );

        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare('SELECT id FROM parejas WHERE id = :id FOR UPDATE');
            $stmt->bindValue(':id', $pareja['id'], PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $this->db->prepare(
                'SELECT COUNT(*)
                 FROM tienda_items
                 WHERE pareja_id = :pareja_id AND activo = TRUE'
            );
            $stmt->bindValue(':pareja_id', $pareja['id'], PDO::PARAM_INT);
            $stmt->execute();

            if ((int) $stmt->fetchColumn() >= self::MAX_PERSONALIZADOS) {
                throw new ApiException(
                    'La pareja ya tiene el máximo de '
                    . self::MAX_PERSONALIZADOS . ' artículos personalizados.',
                    409
                );
            }

            $stmt = $this->db->prepare(
                'INSERT INTO tienda_items
                    (pareja_id, tipo, nombre, descripcion, coste, activo, creado_por_id)
                 VALUES
                    (:pareja_id, :tipo, :nombre, :descripcion, :coste, TRUE, :creado_por_id)'
            );
            $stmt->bindValue(':pareja_id', $pareja['id'], PDO::PARAM_INT);
            $stmt->bindValue(':tipo', $tipo, PDO::PARAM_STR);
            $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);

            if ($descripcion === null) {
                $stmt->bindValue(':descripcion', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
            }

            $stmt->bindValue(':coste', $coste, PDO::PARAM_INT);
            $stmt->bindValue(':creado_por_id', $usuarioId, PDO::PARAM_INT);
            $stmt->execute();

            $itemId = (int) $this->db->lastInsertId();
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }

        $item = $this->getItem($itemId, $pareja['id']);

        if ($item === null) {
            throw new ApiException('No se ha podido recuperar el artículo creado.', 500);
        }

        return $item;
    }

    /**
     * Actualiza un artículo personalizado de la pareja.
     *
     * @return array<string, mixed>
     */
    public function actualizarItem(
        int $usuarioId,
        int $itemId,
        string $tipo,
        string $nombre,
        ?string $descripcion,
        int $coste
    ): array {
        $pareja = $this->getPareja($usuarioId);
        [$tipo, $nombre, $descripcion, $coste] = $this->validarItem(
            $tipo,
            $nombre,
            $descripcion,
            $coste
        );

        $stmt = $this->db->prepare(
            'UPDATE tienda_items
             SET tipo = :tipo,
                 nombre = :nombre,
                 descripcion = :descripcion,
                 coste = :coste
             WHERE id = :id AND pareja_id = :pareja_id AND activo = TRUE'
        );
        $stmt->bindValue(':tipo', $tipo, PDO::PARAM_STR);
        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);

        if ($descripcion === null) {
            $stmt->bindValue(':descripcion', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
        }

        $stmt->bindValue(':coste', $coste, PDO::PARAM_INT);
        $stmt->bindValue(':id', $itemId, PDO::PARAM_INT);
        $stmt->bindValue(':pareja_id', $pareja['id'], PDO::PARAM_INT);
        $stmt->execute();

        $item = $this->getItem($itemId, $pareja['id']);

        if ($item === null || $item['alcance'] !== 'pareja') {
            throw new ApiException('El artículo no existe o no pertenece a tu pareja.', 404);
        }

        return $item;
    }

    /**
     * Desactiva un artículo personalizado. Los globales no se pueden borrar.
     *
     * @return array<string, mixed>
     */
    public function desactivarItem(int $usuarioId, int $itemId): array
    {
        $pareja = $this->getPareja($usuarioId);

        $stmt = $this->db->prepare(
            'UPDATE tienda_items
             SET activo = FALSE
             WHERE id = :id AND pareja_id = :pareja_id AND activo = TRUE'
        );
        $stmt->bindValue(':id', $itemId, PDO::PARAM_INT);
        $stmt->bindValue(':pareja_id', $pareja['id'], PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            throw new ApiException('El artículo no existe, ya está desactivado o es global.', 404);
        }

        $stmt = $this->db->prepare(
            'SELECT i.*, u.nombre AS creado_por_nombre,
                    CASE WHEN i.pareja_id IS NULL THEN \'global\' ELSE \'pareja\' END AS alcance
             FROM tienda_items i
             LEFT JOIN usuarios u ON u.id = i.creado_por_id
             WHERE i.id = :id AND i.pareja_id = :pareja_id
             LIMIT 1'
        );
        $stmt->bindValue(':id', $itemId, PDO::PARAM_INT);
        $stmt->bindValue(':pareja_id', $pareja['id'], PDO::PARAM_INT);
        $stmt->execute();

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($item === false) {
            throw new ApiException('El artículo no existe, ya está desactivado o es global.', 404);
        }

        return self::normalizarItem($item);
    }

    /**
     * Compra un artículo. Crea una tarea pendiente de confirmación por la pareja.
     *
     * @return array<string, mixed>
     */
    public function crearTarea(int $usuarioId, int $itemId): array
    {
        $pareja = $this->getPareja($usuarioId);
        $parejaId = $pareja['id'];
        $destinatario = (new Couple($this->db))->getPartner($usuarioId);

        if ($destinatario === null) {
            throw new ApiException('Debes vincular tu pareja antes de comprar.', 403);
        }

        $destinatarioId = (int) $destinatario['id'];
        $esGanancia = false;

        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare('SELECT id FROM parejas WHERE id = :id FOR UPDATE');
            $stmt->bindValue(':id', $parejaId, PDO::PARAM_INT);
            $stmt->execute();

            $item = $this->getItem($itemId, $parejaId);

            if ($item === null) {
                throw new ApiException('El artículo no existe o no está disponible.', 404);
            }

            $cos = (int) $item['coste'];
            $tipo = (string) $item['tipo'];
            $esGanancia = $tipo === 'ganancia';
            $eventoId = null;

            if (!$esGanancia) {
                $saldo = (new Balance($this->db))->getSaldoUsuario($usuarioId);

                if ($saldo < $cos) {
                    throw new ApiException('No tienes coins suficientes para este artículo.', 409);
                }

                $eventoId = (new Event($this->db))->create(
                    $usuarioId,
                    $usuarioId,
                    null,
                    (string) $item['nombre'],
                    api_hoy()->format('Y-m-d'),
                    $cos,
                    'resta',
                    'tienda'
                );
            }

            $stmt = $this->db->prepare(
                'INSERT INTO pareja_tareas
                    (pareja_id, item_id, tipo, titulo, coste, estado,
                     solicitante_id, destinatario_id, evento_id)
                 VALUES
                    (:pareja_id, :item_id, :tipo, :titulo, :coste, \'pendiente\',
                     :solicitante_id, :destinatario_id, :evento_id)'
            );
            $stmt->bindValue(':pareja_id', $parejaId, PDO::PARAM_INT);
            $stmt->bindValue(':item_id', $itemId, PDO::PARAM_INT);
            $stmt->bindValue(':tipo', $tipo, PDO::PARAM_STR);
            $stmt->bindValue(':titulo', (string) $item['nombre'], PDO::PARAM_STR);
            $stmt->bindValue(':coste', $cos, PDO::PARAM_INT);
            $stmt->bindValue(':solicitante_id', $usuarioId, PDO::PARAM_INT);
            $stmt->bindValue(':destinatario_id', $destinatarioId, PDO::PARAM_INT);

            if ($eventoId === null) {
                $stmt->bindValue(':evento_id', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':evento_id', $eventoId, PDO::PARAM_INT);
            }

            $stmt->execute();

            $tareaId = (int) $this->db->lastInsertId();
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }

        $tarea = $this->getTareaInterna($parejaId, $tareaId);

        if ($tarea === null) {
            throw new ApiException('No se ha podido recuperar la tarea creada.', 500);
        }

        return $tarea;
    }

    /**
     * Lista las tareas de la pareja.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTareas(int $usuarioId, ?string $estado = null): array
    {
        $pareja = $this->getPareja($usuarioId);

        if ($estado !== null && !in_array($estado, self::ESTADOS_TAREA, true)) {
            throw new ApiException('El estado de tarea no es válido.', 400);
        }

        $sql = self::SQL_TAREA . ' WHERE t.pareja_id = :pareja_id';
        $parametros = [':pareja_id' => $pareja['id']];

        if ($estado !== null) {
            $sql .= ' AND t.estado = :estado';
            $parametros[':estado'] = $estado;
        }

        $sql .= ' ORDER BY t.created_at DESC, t.id DESC';

        $stmt = $this->db->prepare($sql);

        foreach ($parametros as $nombre => $valor) {
            $stmt->bindValue($nombre, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }

        $stmt->execute();

        return array_map(
            static fn (array $tarea): array => self::normalizarTarea($tarea),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    /**
     * La pareja confirma la tarea. En las tareas de ganancia es aquí cuando se
     * crea el evento de suma que acredita las coins al solicitante.
     *
     * @return array<string, mixed>
     */
    public function confirmarTarea(int $usuarioId, int $tareaId): array
    {
        $pareja = $this->getPareja($usuarioId);

        $this->db->beginTransaction();

        try {
            $tarea = $this->getTareaInterna($pareja['id'], $tareaId, true);

            if ($tarea === null) {
                throw new ApiException('La tarea no existe.', 404);
            }

            if ($tarea['estado'] !== 'pendiente') {
                throw new ApiException('La tarea ya está resuelta.', 409);
            }

            if ((int) $tarea['destinatario_id'] !== $usuarioId) {
                throw new ApiException('Solo tu pareja puede confirmar esta tarea.', 403);
            }

            // El evento de suma acredita a la pareja del titular, así que el
            // titular debe ser el destinatario para que cobre el solicitante.
            if ($tarea['tipo'] === 'ganancia' && $tarea['evento_id'] === null) {
                $eventoId = (new Event($this->db))->create(
                    (int) $tarea['destinatario_id'],
                    $usuarioId,
                    null,
                    (string) $tarea['titulo'],
                    api_hoy()->format('Y-m-d'),
                    (int) $tarea['coste'],
                    'suma',
                    'tarea'
                );

                $stmt = $this->db->prepare(
                    'UPDATE pareja_tareas SET evento_id = :evento_id WHERE id = :id'
                );
                $stmt->bindValue(':evento_id', $eventoId, PDO::PARAM_INT);
                $stmt->bindValue(':id', $tareaId, PDO::PARAM_INT);
                $stmt->execute();
            }

            $stmt = $this->db->prepare(
                'UPDATE pareja_tareas
                 SET estado = \'cumplida\',
                     resuelto_por_id = :resuelto_por_id,
                     resuelto_en = NOW()
                 WHERE id = :id'
            );
            $stmt->bindValue(':resuelto_por_id', $usuarioId, PDO::PARAM_INT);
            $stmt->bindValue(':id', $tareaId, PDO::PARAM_INT);
            $stmt->execute();

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }

        $tarea = $this->getTareaInterna($pareja['id'], $tareaId);

        if ($tarea === null) {
            throw new ApiException('No se ha podido recuperar la tarea.', 500);
        }

        return $tarea;
    }

    /**
     * La pareja rechaza una tarea pendiente. Si había un cargo, se anula y se
     * devuelven las coins.
     *
     * @return array<string, mixed>
     */
    public function rechazarTarea(int $usuarioId, int $tareaId): array
    {
        return $this->resolverTarea($usuarioId, $tareaId, 'rechazada');
    }

    /**
     * El solicitante cancela su tarea pendiente. Si había un cargo, se anula y
     * se devuelven las coins.
     *
     * @return array<string, mixed>
     */
    public function cancelarTarea(int $usuarioId, int $tareaId): array
    {
        return $this->resolverTarea($usuarioId, $tareaId, 'cancelada');
    }

    /**
     * @return array<string, mixed>
     */
    private function resolverTarea(int $usuarioId, int $tareaId, string $nuevoEstado): array
    {
        $pareja = $this->getPareja($usuarioId);

        $this->db->beginTransaction();

        try {
            $tarea = $this->getTareaInterna($pareja['id'], $tareaId, true);

            if ($tarea === null) {
                throw new ApiException('La tarea no existe.', 404);
            }

            if ($tarea['estado'] !== 'pendiente') {
                throw new ApiException('La tarea ya está resuelta.', 409);
            }

            if ($nuevoEstado === 'rechazada' && (int) $tarea['destinatario_id'] !== $usuarioId) {
                throw new ApiException('Solo tu pareja puede rechazar esta tarea.', 403);
            }

            if ($nuevoEstado === 'cancelada' && (int) $tarea['solicitante_id'] !== $usuarioId) {
                throw new ApiException('Solo quien solicitó la tarea puede cancelarla.', 403);
            }

            if ($tarea['evento_id'] !== null) {
                (new Event($this->db))->anular((int) $tarea['evento_id']);
            }

            $stmt = $this->db->prepare(
                'UPDATE pareja_tareas
                 SET estado = :estado,
                     resuelto_por_id = :resuelto_por_id,
                     resuelto_en = NOW()
                 WHERE id = :id'
            );
            $stmt->bindValue(':estado', $nuevoEstado, PDO::PARAM_STR);
            $stmt->bindValue(':resuelto_por_id', $usuarioId, PDO::PARAM_INT);
            $stmt->bindValue(':id', $tareaId, PDO::PARAM_INT);
            $stmt->execute();

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }

        $tarea = $this->getTareaInterna($pareja['id'], $tareaId);

        if ($tarea === null) {
            throw new ApiException('No se ha podido recuperar la tarea.', 500);
        }

        return $tarea;
    }

    private const SQL_TAREA = 'SELECT t.*,
                                      i.nombre AS item_nombre,
                                      i.descripcion AS item_descripcion,
                                      s.nombre AS solicitante_nombre,
                                      d.nombre AS destinatario_nombre,
                                      r.nombre AS resuelto_por_nombre
                               FROM pareja_tareas t
                               INNER JOIN tienda_items i ON i.id = t.item_id
                               INNER JOIN usuarios s ON s.id = t.solicitante_id
                               INNER JOIN usuarios d ON d.id = t.destinatario_id
                               LEFT JOIN usuarios r ON r.id = t.resuelto_por_id';

    /**
     * @return array<string, mixed>|null
     */
    private function getTareaInterna(int $parejaId, int $tareaId, bool $bloquear = false): ?array
    {
        if ($bloquear) {
            $stmt = $this->db->prepare(
                'SELECT * FROM pareja_tareas
                 WHERE id = :id AND pareja_id = :pareja_id
                 LIMIT 1 FOR UPDATE'
            );
            $stmt->bindValue(':id', $tareaId, PDO::PARAM_INT);
            $stmt->bindValue(':pareja_id', $parejaId, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->fetch(PDO::FETCH_ASSOC) === false) {
                return null;
            }
        }

        $stmt = $this->db->prepare(
            self::SQL_TAREA . ' WHERE t.id = :id AND t.pareja_id = :pareja_id LIMIT 1'
        );
        $stmt->bindValue(':id', $tareaId, PDO::PARAM_INT);
        $stmt->bindValue(':pareja_id', $parejaId, PDO::PARAM_INT);
        $stmt->execute();

        $tarea = $stmt->fetch(PDO::FETCH_ASSOC);

        return $tarea === false ? null : self::normalizarTarea($tarea);
    }

    /**
     * @return array{id: int, usuario_1_id: int, usuario_2_id: int}
     */
    private function getPareja(int $usuarioId): array
    {
        $pareja = (new Couple($this->db))->getParejaDeUsuario($usuarioId);

        if ($pareja === null) {
            throw new ApiException('Debes vincular tu pareja antes de usar la tienda.', 403);
        }

        return [
            'id' => $pareja['id'],
            'usuario_1_id' => $pareja['usuario_1_id'],
            'usuario_2_id' => $pareja['usuario_2_id'],
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: ?string, 3: int}
     */
    private function validarItem(
        string $tipo,
        string $nombre,
        ?string $descripcion,
        int $coste
    ): array {
        $tipo = strtolower(trim($tipo));
        $nombre = trim($nombre);
        $descripcion = $descripcion === null ? null : trim($descripcion);

        if (!in_array($tipo, self::TIPOS, true)) {
            throw new ApiException(
                'El tipo debe ser "recompensa", "permiso" o "ganancia".',
                400
            );
        }

        if ($nombre === '' || mb_strlen($nombre) > 100) {
            throw new ApiException('El nombre es obligatorio y no puede superar 100 caracteres.', 400);
        }

        if ($descripcion !== null && mb_strlen($descripcion) > 255) {
            throw new ApiException('La descripción no puede superar 255 caracteres.', 400);
        }

        if ($coste < 1 || $coste > self::COSTE_MAXIMO) {
            throw new ApiException(
                'El coste debe estar entre 1 y ' . self::COSTE_MAXIMO . ' coins.',
                400
            );
        }

        return [$tipo, $nombre, $descripcion, $coste];
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private static function normalizarItem(array $item): array
    {
        foreach (['id', 'pareja_id', 'coste', 'creado_por_id'] as $campo) {
            if (array_key_exists($campo, $item)) {
                $item[$campo] = $item[$campo] === null ? null : (int) $item[$campo];
            }
        }

        if (array_key_exists('activo', $item)) {
            $item['activo'] = User::esActivo($item['activo']);
        }

        if (!array_key_exists('alcance', $item)) {
            $item['alcance'] = $item['pareja_id'] === null ? 'global' : 'pareja';
        }

        return $item;
    }

    /**
     * @param array<string, mixed> $tarea
     * @return array<string, mixed>
     */
    private static function normalizarTarea(array $tarea): array
    {
        foreach ([
            'id',
            'pareja_id',
            'item_id',
            'coste',
            'solicitante_id',
            'destinatario_id',
            'resuelto_por_id',
            'evento_id',
        ] as $campo) {
            if (array_key_exists($campo, $tarea)) {
                $tarea[$campo] = $tarea[$campo] === null ? null : (int) $tarea[$campo];
            }
        }

        return $tarea;
    }
}
