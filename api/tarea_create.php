<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['POST']);

$datos = api_datos();
$itemId = api_entero_positivo($datos['item_id'] ?? null);

if ($itemId === null) {
    api_error(400, 'El ID del artículo no es válido.');
}

try {
    $usuario = api_exigir_pareja();
    $db = api_db();

    $tarea = (new Shop($db))->crearTarea((int) $usuario['id'], $itemId);

    api_responder(201, [
        'success' => true,
        'message' => $tarea['tipo'] === 'ganancia'
            ? 'Tarea creada. Tu pareja debe confirmarla para que recibas las coins.'
            : 'Compra registrada. Tu pareja debe confirmar la tarea.',
        'data' => $tarea,
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins tarea_create: ' . $e->getMessage());
    api_error(500, 'No se ha podido crear la tarea.');
}
