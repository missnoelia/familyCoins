<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['GET']);

$granularidad = 'mes';

try {
    $usuario = api_exigir_pareja();
    $db = api_db();
    $usuarioId = (int) $usuario['id'];

    if (isset($_GET['granularidad']) && $_GET['granularidad'] !== '') {
        $granularidad = api_texto($_GET['granularidad'], 20);
    }

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

    $usuarioFiltro = null;

    if (isset($_GET['usuario_id']) && $_GET['usuario_id'] !== '') {
        $usuarioFiltro = api_entero_positivo($_GET['usuario_id']);

        if ($usuarioFiltro === null) {
            api_error(400, 'El usuario del filtro no es válido.');
        }

        if (!(new Event($db))->pertenecePareja($usuarioId, $usuarioFiltro)) {
            api_error(403, 'No puedes consultar las estadísticas de ese usuario.');
        }
    }

    $resultado = (new Stats($db))->getEstadisticas(
        $usuarioId,
        $granularidad,
        $desde,
        $hasta,
        $usuarioFiltro
    );

    $resultado['meta']['usuario_id'] = $usuarioFiltro;

    api_responder(200, [
        'success' => true,
        'data' => $resultado['data'],
        'meta' => $resultado['meta'],
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins stats: ' . $e->getMessage());
    api_error(500, 'No se han podido calcular las estadísticas.');
}
