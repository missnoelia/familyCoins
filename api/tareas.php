<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['GET']);

$estado = null;

if (isset($_GET['estado']) && $_GET['estado'] !== '') {
    $estado = strtolower(api_texto($_GET['estado'], 20));

    if (!in_array($estado, Shop::ESTADOS_TAREA, true)) {
        api_error(400, 'El estado de tarea no es válido.');
    }
}

try {
    $usuario = api_exigir_pareja();
    $db = api_db();

    $tareas = (new Shop($db))->getTareas((int) $usuario['id'], $estado);

    api_responder(200, [
        'success' => true,
        'data' => $tareas,
        'meta' => [
            'total' => count($tareas),
            'estado' => $estado,
        ],
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins tareas: ' . $e->getMessage());
    api_error(500, 'No se han podido consultar las tareas.');
}
