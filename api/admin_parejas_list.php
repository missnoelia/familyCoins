<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['GET']);

try {
    api_exigir_admin();
    $db = api_db();
    $paginacion = api_paginacion(50, 200);

    $filtros = [
        'limite' => $paginacion['limite'],
        'offset' => $paginacion['offset'],
    ];

    if (isset($_GET['q']) && $_GET['q'] !== '') {
        $filtros['q'] = api_texto($_GET['q'], 100);
    }

    $resultado = (new Couple($db))->adminList($filtros);

    api_responder(200, api_paginado($resultado['data'], $resultado['total'], $paginacion));
} catch (PDOException $e) {
    error_log('FamilyCoins admin_parejas_list: ' . $e->getMessage());
    api_error(500, 'No se han podido consultar las parejas.');
}
