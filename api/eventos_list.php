<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['GET']);

$filtros = [];

try {
    $usuario = api_exigir_pareja();
    $db = api_db();
    $usuarioId = (int) $usuario['id'];

    $coupleModel = new Couple($db);
    $pareja = $coupleModel->getPartner($usuarioId);
    $usuariosPermitidos = [$usuarioId];

    if ($pareja !== null) {
        $usuariosPermitidos[] = (int) $pareja['id'];
    }

    if (isset($_GET['usuario_id']) && $_GET['usuario_id'] !== '') {
        $filtroUsuario = api_entero_positivo($_GET['usuario_id']);

        if ($filtroUsuario === null) {
            api_error(400, 'El usuario del filtro no es válido.');
        }

        if (!in_array($filtroUsuario, $usuariosPermitidos, true)) {
            api_error(403, 'No puedes consultar eventos de ese usuario.');
        }

        $filtros['usuario_id'] = $filtroUsuario;
    }

    if (isset($_GET['efecto']) && $_GET['efecto'] !== '') {
        $efecto = strtolower(api_texto($_GET['efecto'], 10));

        if (!in_array($efecto, ['resta', 'suma'], true)) {
            api_error(400, 'El efecto debe ser "resta" o "suma".');
        }

        $filtros['efecto'] = $efecto;
    }

    if (isset($_GET['desde']) && $_GET['desde'] !== '') {
        $desde = api_fecha($_GET['desde']);

        if ($desde === null) {
            api_error(400, 'La fecha "desde" no es válida.');
        }

        $filtros['desde'] = $desde;
    }

    if (isset($_GET['hasta']) && $_GET['hasta'] !== '') {
        $hasta = api_fecha($_GET['hasta']);

        if ($hasta === null) {
            api_error(400, 'La fecha "hasta" no es válida.');
        }

        $filtros['hasta'] = $hasta;
    }

    if (isset($filtros['desde'], $filtros['hasta']) && $filtros['desde'] > $filtros['hasta']) {
        api_error(400, 'La fecha "desde" no puede ser posterior a "hasta".');
    }

    if (isset($_GET['nombre']) && $_GET['nombre'] !== '') {
        $filtros['nombre'] = api_texto($_GET['nombre'], 100);
    }

    if (isset($_GET['anulado']) && $_GET['anulado'] !== '') {
        $anulado = api_booleano($_GET['anulado']);

        if ($anulado === null) {
            api_error(400, 'El filtro "anulado" debe ser true o false.');
        }

        $filtros['anulado'] = $anulado;
    }

    if (isset($_GET['orden']) && $_GET['orden'] !== '') {
        $orden = strtolower(api_texto($_GET['orden'], 20));

        if (!in_array($orden, ['fecha', 'coste', 'persona', 'usuario', 'nombre', 'id'], true)) {
            api_error(400, 'El criterio de ordenación no es válido.');
        }

        $filtros['orden'] = $orden;
    }

    if (isset($_GET['dir']) && $_GET['dir'] !== '') {
        $direccion = strtolower(api_texto($_GET['dir'], 4));

        if (!in_array($direccion, ['asc', 'desc'], true)) {
            api_error(400, 'La dirección de ordenación debe ser "asc" o "desc".');
        }

        $filtros['dir'] = $direccion;
    }

    $eventos = (new Event($db))->getAll($usuarioId, $filtros);

    api_responder(200, [
        'success' => true,
        'data' => $eventos,
        'meta' => [
            'total' => count($eventos),
            'filtros' => $filtros,
        ],
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins eventos_list: ' . $e->getMessage());
    api_error(500, 'No se han podido consultar los eventos.');
}
