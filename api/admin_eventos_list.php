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

    foreach (['usuario_id', 'pareja_id'] as $campo) {
        if (isset($_GET[$campo]) && $_GET[$campo] !== '') {
            $valor = api_entero_positivo($_GET[$campo]);

            if ($valor === null) {
                api_error(400, 'El filtro "' . $campo . '" no es válido.');
            }

            $filtros[$campo] = $valor;
        }
    }

    if (isset($_GET['q']) && $_GET['q'] !== '') {
        $filtros['q'] = api_texto($_GET['q'], 100);
    }

    if (isset($_GET['efecto']) && $_GET['efecto'] !== '') {
        $efecto = strtolower(api_texto($_GET['efecto'], 10));

        if (!in_array($efecto, ['resta', 'suma'], true)) {
            api_error(400, 'El efecto debe ser "resta" o "suma".');
        }

        $filtros['efecto'] = $efecto;
    }

    foreach (['desde', 'hasta'] as $campo) {
        if (isset($_GET[$campo]) && $_GET[$campo] !== '') {
            $fecha = api_fecha($_GET[$campo]);

            if ($fecha === null) {
                api_error(400, 'La fecha "' . $campo . '" no es válida.');
            }

            $filtros[$campo] = $fecha;
        }
    }

    if (isset($filtros['desde'], $filtros['hasta']) && $filtros['desde'] > $filtros['hasta']) {
        api_error(400, 'La fecha "desde" no puede ser posterior a "hasta".');
    }

    if (isset($_GET['anulado']) && $_GET['anulado'] !== '') {
        $anulado = api_booleano($_GET['anulado']);

        if ($anulado === null) {
            api_error(400, 'El filtro "anulado" debe ser true o false.');
        }

        $filtros['anulado'] = $anulado;
    }

    $resultado = (new Event($db))->adminList($filtros);

    api_responder(200, api_paginado($resultado['data'], $resultado['total'], $paginacion));
} catch (PDOException $e) {
    error_log('FamilyCoins admin_eventos_list: ' . $e->getMessage());
    api_error(500, 'No se han podido consultar los eventos.');
}
