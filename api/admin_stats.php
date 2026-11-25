<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['GET']);

try {
    api_exigir_admin();
    $db = api_db();

    $granularidad = isset($_GET['granularidad']) && $_GET['granularidad'] !== ''
        ? api_texto($_GET['granularidad'], 20)
        : 'mes';

    $desde = null;

    if (isset($_GET['desde']) && $_GET['desde'] !== '') {
        $desde = api_fecha($_GET['desde']);

        if ($desde === null) {
            api_error(400, 'La fecha "desde" no es válida.');
        }
    }

    $hasta = null;

    if (isset($_GET['hasta']) && $_GET['hasta'] !== '') {
        $hasta = api_fecha($_GET['hasta']);

        if ($hasta === null) {
            api_error(400, 'La fecha "hasta" no es válida.');
        }
    }

    $usuarioId = null;

    if (isset($_GET['usuario_id']) && $_GET['usuario_id'] !== '') {
        $usuarioId = api_entero_positivo($_GET['usuario_id']);

        if ($usuarioId === null) {
            api_error(400, 'El filtro "usuario_id" no es válido.');
        }
    }

    $parejaId = null;

    if (isset($_GET['pareja_id']) && $_GET['pareja_id'] !== '') {
        $parejaId = api_entero_positivo($_GET['pareja_id']);

        if ($parejaId === null) {
            api_error(400, 'El filtro "pareja_id" no es válido.');
        }

        if ((new Couple($db))->adminGet($parejaId) === null) {
            api_error(404, 'La pareja no existe.');
        }
    }

    $resultado = (new Stats($db))->getEstadisticasGlobales(
        $granularidad,
        $desde,
        $hasta,
        $usuarioId,
        $parejaId
    );

    $resultado['meta']['usuario_id'] = $usuarioId;
    $resultado['meta']['pareja_id'] = $parejaId;

    api_responder(200, [
        'success' => true,
        'data' => $resultado['data'],
        'meta' => $resultado['meta'],
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins admin_stats: ' . $e->getMessage());
    api_error(500, 'No se han podido calcular las estadísticas.');
}
