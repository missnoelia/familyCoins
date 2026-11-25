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

    if (isset($_GET['rol']) && $_GET['rol'] !== '') {
        $rol = strtolower(api_texto($_GET['rol'], 30));

        if (!in_array($rol, ['usuario', 'superadmin'], true)) {
            api_error(400, 'El filtro de rol no es válido.');
        }

        $filtros['rol'] = $rol;
    }

    if (isset($_GET['activo']) && $_GET['activo'] !== '') {
        $activo = api_booleano($_GET['activo']);

        if ($activo === null) {
            api_error(400, 'El filtro "activo" debe ser true o false.');
        }

        $filtros['activo'] = $activo;
    }

    if (isset($_GET['con_pareja']) && $_GET['con_pareja'] !== '') {
        $conPareja = api_booleano($_GET['con_pareja']);

        if ($conPareja === null) {
            api_error(400, 'El filtro "con_pareja" debe ser true o false.');
        }

        $filtros['con_pareja'] = $conPareja;
    }

    $resultado = (new User($db))->adminList($filtros);

    api_responder(200, api_paginado($resultado['data'], $resultado['total'], $paginacion));
} catch (PDOException $e) {
    error_log('FamilyCoins admin_usuarios_list: ' . $e->getMessage());
    api_error(500, 'No se han podido consultar los usuarios.');
}
