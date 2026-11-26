<?php
declare(strict_types=1);

/**
 * Estadísticas agregadas por mes, trimestre o año.
 */
class Stats
{
    private const MAX_PERIODOS = 120;

    private const MESES_CORTOS = [
        1 => 'ene',
        2 => 'feb',
        3 => 'mar',
        4 => 'abr',
        5 => 'may',
        6 => 'jun',
        7 => 'jul',
        8 => 'ago',
        9 => 'sep',
        10 => 'oct',
        11 => 'nov',
        12 => 'dic',
    ];

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Estadísticas de la pareja del usuario.
     *
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function getEstadisticas(
        int $usuarioId,
        string $granularidad = 'mes',
        ?string $desde = null,
        ?string $hasta = null,
        ?int $usuarioFiltro = null
    ): array {
        $granularidad = self::normalizarGranularidad($granularidad);
        [$desde, $hasta, $periodos] = $this->prepararPeriodos($granularidad, $desde, $hasta);

        $filtros = ['desde' => $desde, 'hasta' => $hasta];

        if ($usuarioFiltro !== null) {
            $filtros['usuario_id'] = $usuarioFiltro;
        }

        $eventos = (new Event($this->db))->getAll($usuarioId, $filtros);
        $recargas = (new Recharge($this->db))->getAll($usuarioId);

        return $this->calcular(
            $periodos,
            $eventos,
            $recargas,
            $granularidad,
            $desde,
            $hasta,
            $usuarioFiltro
        );
    }

    /**
     * Estadísticas globales para el panel de administración.
     *
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function getEstadisticasGlobales(
        string $granularidad = 'mes',
        ?string $desde = null,
        ?string $hasta = null,
        ?int $usuarioId = null,
        ?int $parejaId = null
    ): array {
        $granularidad = self::normalizarGranularidad($granularidad);
        [$desde, $hasta, $periodos] = $this->prepararPeriodos($granularidad, $desde, $hasta);

        $parametros = [':desde' => $desde, ':hasta' => $hasta];
        $filtros = '';

        if ($usuarioId !== null) {
            $filtros .= ' AND e.usuario_id = :usuario_id';
            $parametros[':usuario_id'] = $usuarioId;
        }

        if ($parejaId !== null) {
            $filtros .= ' AND e.usuario_id IN (
                            SELECT usuario_1_id FROM parejas WHERE id = :pareja1
                            UNION
                            SELECT usuario_2_id FROM parejas WHERE id = :pareja2
                          )';
            $parametros[':pareja1'] = $parejaId;
            $parametros[':pareja2'] = $parejaId;
        }

        $stmt = $this->db->prepare(
            'SELECT e.fecha, e.coste, e.efecto, e.anulado
             FROM eventos e
             WHERE e.fecha >= :desde AND e.fecha <= :hasta' . $filtros
        );

        foreach ($parametros as $nombre => $valor) {
            $stmt->bindValue($nombre, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }

        $stmt->execute();
        $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $parametros = [':desde' => $desde, ':hasta' => $hasta];
        $filtros = '';

        if ($usuarioId !== null) {
            $filtros .= ' AND r.usuario_id = :usuario_id';
            $parametros[':usuario_id'] = $usuarioId;
        }

        if ($parejaId !== null) {
            $filtros .= ' AND r.usuario_id IN (
                            SELECT usuario_1_id FROM parejas WHERE id = :pareja1
                            UNION
                            SELECT usuario_2_id FROM parejas WHERE id = :pareja2
                          )';
            $parametros[':pareja1'] = $parejaId;
            $parametros[':pareja2'] = $parejaId;
        }

        $stmt = $this->db->prepare(
            'SELECT r.usuario_id, r.importe, r.fecha
             FROM recargas r
             WHERE r.fecha >= :desde AND r.fecha <= :hasta' . $filtros
        );

        foreach ($parametros as $nombre => $valor) {
            $stmt->bindValue($nombre, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }

        $stmt->execute();
        $recargas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->calcular(
            $periodos,
            $eventos,
            $recargas,
            $granularidad,
            $desde,
            $hasta,
            null
        );
    }

    /**
     * @param array<int, array<string, mixed>> $periodos
     * @param array<int, array<string, mixed>> $eventos
     * @param array<int, array<string, mixed>> $recargas
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    private function calcular(
        array $periodos,
        array $eventos,
        array $recargas,
        string $granularidad,
        string $desde,
        string $hasta,
        ?int $usuarioFiltro
    ): array {
        $buckets = [];

        foreach ($periodos as $periodo) {
            $buckets[$periodo['periodo']] = $periodo + [
                'eventos' => 0,
                'total_resta' => 0,
                'total_suma' => 0,
                'neto' => 0,
                'recargas' => 0,
                'variacion' => 0,
            ];
        }

        foreach ($eventos as $evento) {
            if (Event::esAnulado($evento['anulado'] ?? false)) {
                continue;
            }

            if ($usuarioFiltro !== null && (int) $evento['usuario_id'] !== $usuarioFiltro) {
                continue;
            }

            $fecha = (string) $evento['fecha'];

            if ($fecha < $desde || $fecha > $hasta) {
                continue;
            }

            $clave = self::claveDesdeFecha($fecha, $granularidad);

            if (!isset($buckets[$clave])) {
                continue;
            }

            $coste = (int) $evento['coste'];
            $efecto = strtolower((string) $evento['efecto']);

            $buckets[$clave]['eventos']++;

            if ($efecto === 'resta') {
                $buckets[$clave]['total_resta'] += $coste;
            } elseif ($efecto === 'suma') {
                $buckets[$clave]['total_suma'] += $coste;
            }
        }

        foreach ($recargas as $recarga) {
            if ($usuarioFiltro !== null && (int) $recarga['usuario_id'] !== $usuarioFiltro) {
                continue;
            }

            $fecha = (string) $recarga['fecha'];

            if ($fecha < $desde || $fecha > $hasta) {
                continue;
            }

            $clave = self::claveDesdeFecha($fecha, $granularidad);

            if (!isset($buckets[$clave])) {
                continue;
            }

            $buckets[$clave]['recargas'] += (int) $recarga['importe'];
        }

        $totales = [
            'total_eventos' => 0,
            'total_resta' => 0,
            'total_suma' => 0,
            'total_recargas' => 0,
        ];

        foreach ($buckets as &$bucket) {
            $bucket['neto'] = $bucket['total_suma'] - $bucket['total_resta'];
            $bucket['variacion'] = $bucket['recargas'] + $bucket['neto'];

            $totales['total_eventos'] += $bucket['eventos'];
            $totales['total_resta'] += $bucket['total_resta'];
            $totales['total_suma'] += $bucket['total_suma'];
            $totales['total_recargas'] += $bucket['recargas'];
        }

        unset($bucket);

        return [
            'data' => array_values($buckets),
            'meta' => [
                'granularidad' => $granularidad,
                'desde' => $desde,
                'hasta' => $hasta,
                'total_periodos' => count($buckets),
            ] + $totales,
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: array<int, array<string, string>>}
     */
    private function prepararPeriodos(
        string $granularidad,
        ?string $desde,
        ?string $hasta
    ): array {
        $hastaDate = $this->fechaODefecto($hasta, new DateTimeImmutable('today'));

        if ($hastaDate === null) {
            throw new ApiException('La fecha "hasta" no es válida.', 400);
        }

        if ($desde === null || $desde === '') {
            $inicioActual = self::inicioPeriodo($hastaDate, $granularidad);

            if ($granularidad === 'mes') {
                $desdeDate = $inicioActual->modify('-11 months');
            } elseif ($granularidad === 'trimestre') {
                $desdeDate = $inicioActual->modify('-21 months');
            } else {
                $desdeDate = $inicioActual->setDate(
                    (int) $inicioActual->format('Y') - 4,
                    1,
                    1
                );
            }
        } else {
            $desdeDate = $this->fechaODefecto($desde, null);
        }

        if ($desdeDate === null) {
            throw new ApiException('La fecha "desde" no es válida.', 400);
        }

        if ($desdeDate > $hastaDate) {
            throw new ApiException('La fecha "desde" no puede ser posterior a "hasta".', 400);
        }

        $periodos = [];
        $cursor = self::inicioPeriodo($desdeDate, $granularidad);

        while ($cursor <= $hastaDate) {
            if (count($periodos) >= self::MAX_PERIODOS) {
                throw new ApiException('El rango de fechas es demasiado amplio.', 400);
            }

            $periodos[] = [
                'periodo' => self::clavePeriodo($cursor, $granularidad),
                'etiqueta' => self::etiquetaPeriodo($cursor, $granularidad),
                'inicio' => $cursor->format('Y-m-d'),
                'fin' => self::finPeriodo($cursor, $granularidad)->format('Y-m-d'),
            ];

            $cursor = self::siguientePeriodo($cursor, $granularidad);
        }

        return [$desdeDate->format('Y-m-d'), $hastaDate->format('Y-m-d'), $periodos];
    }

    private function fechaODefecto(?string $fecha, ?DateTimeImmutable $defecto): ?DateTimeImmutable
    {
        if ($fecha === null || $fecha === '') {
            return $defecto;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        $errores = DateTimeImmutable::getLastErrors();

        if ($date === false) {
            return null;
        }

        if (is_array($errores) && ($errores['warning_count'] > 0 || $errores['error_count'] > 0)) {
            return null;
        }

        return $date->format('Y-m-d') === $fecha ? $date : null;
    }

    private static function normalizarGranularidad(string $granularidad): string
    {
        $valor = strtolower(trim($granularidad));

        if (in_array($valor, ['año', 'ano', 'años', 'anos'], true)) {
            return 'ano';
        }

        if ($valor === 'mes' || $valor === 'trimestre') {
            return $valor;
        }

        throw new ApiException('La granularidad debe ser mes, trimestre o año.', 400);
    }

    private static function inicioPeriodo(DateTimeImmutable $fecha, string $granularidad): DateTimeImmutable
    {
        $anio = (int) $fecha->format('Y');
        $mes = (int) $fecha->format('n');

        if ($granularidad === 'mes') {
            return $fecha->setDate($anio, $mes, 1);
        }

        if ($granularidad === 'trimestre') {
            $mesInicio = intdiv($mes - 1, 3) * 3 + 1;

            return $fecha->setDate($anio, $mesInicio, 1);
        }

        return $fecha->setDate($anio, 1, 1);
    }

    private static function finPeriodo(DateTimeImmutable $inicio, string $granularidad): DateTimeImmutable
    {
        if ($granularidad === 'mes') {
            return $inicio->modify('last day of this month');
        }

        if ($granularidad === 'trimestre') {
            return $inicio->modify('+2 months')->modify('last day of this month');
        }

        return $inicio->setDate((int) $inicio->format('Y'), 12, 31);
    }

    private static function siguientePeriodo(DateTimeImmutable $inicio, string $granularidad): DateTimeImmutable
    {
        if ($granularidad === 'mes') {
            return $inicio->modify('+1 month');
        }

        if ($granularidad === 'trimestre') {
            return $inicio->modify('+3 months');
        }

        return $inicio->modify('+1 year');
    }

    private static function clavePeriodo(DateTimeImmutable $fecha, string $granularidad): string
    {
        if ($granularidad === 'mes') {
            return $fecha->format('Y-m');
        }

        if ($granularidad === 'trimestre') {
            $trimestre = intdiv((int) $fecha->format('n') - 1, 3) + 1;

            return $fecha->format('Y') . '-T' . $trimestre;
        }

        return $fecha->format('Y');
    }

    private static function etiquetaPeriodo(DateTimeImmutable $fecha, string $granularidad): string
    {
        $mes = (int) $fecha->format('n');

        if ($granularidad === 'mes') {
            return self::MESES_CORTOS[$mes] . ' ' . $fecha->format('Y');
        }

        if ($granularidad === 'trimestre') {
            $trimestre = intdiv($mes - 1, 3) + 1;

            return 'T' . $trimestre . ' ' . $fecha->format('Y');
        }

        return $fecha->format('Y');
    }

    private static function claveDesdeFecha(string $fecha, string $granularidad): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);

        return $date === false ? '' : self::clavePeriodo($date, $granularidad);
    }
}
