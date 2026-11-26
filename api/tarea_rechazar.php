<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['POST']);

$datos = api_datos();
$id = api_entero_positivo($datos['id'] ?? null);

if ($id === null) {
    api_error(400, 'El ID de la tarea no es válido.');
}

try {
    $usuario = api_exigir_pareja();
    $db = api_db();

    $tarea = (new Shop($db))->rechazarTarea((int) $usuario['id'], $id);

    api_responder(200, [
        'success' => true,
        'message' => 'Tarea rechazada. Si había un cargo, las coins se han devuelto.',
        'data' => $tarea,
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins tarea_rechazar: ' . $e->getMessage());
    api_error(500, 'No se ha podido rechazar la tarea.');
}
