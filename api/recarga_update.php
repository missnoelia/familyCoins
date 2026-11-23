<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['POST']);

$datos = api_datos();

$id = api_entero_positivo($datos['id'] ?? null);
$importe = api_entero_positivo($datos['importe'] ?? null);
$fecha = api_fecha(api_texto($datos['fecha'] ?? '', 10));
$motivo = api_texto($datos['motivo'] ?? '', 255);

if ($id === null) {
    api_error(400, 'El ID de la recarga no es válido.');
}

if ($importe === null) {
    api_error(400, 'El importe debe ser un número entero mayor que cero.');
}

if ($fecha === null) {
    api_error(400, 'La fecha de la recarga no es válida.');
}

try {
    $usuario = api_exigir_pareja();
    $db = api_db();
    $usuarioId = (int) $usuario['id'];
    $rechargeModel = new Recharge($db);

    $recarga = $rechargeModel->getById($id);

    if ($recarga === null || !(new Couple($db))->esDeLaPareja($usuarioId, (int) $recarga['usuario_id'])) {
        api_error(404, 'Recarga no encontrada.');
    }

    $actualizada = $rechargeModel->update($id, $importe, $fecha, $motivo === '' ? null : $motivo);

    if (!$actualizada && $rechargeModel->getById($id) === null) {
        api_error(404, 'La recarga ha sido eliminada mientras se actualizaba.');
    }

    api_responder(200, [
        'success' => true,
        'message' => 'Recarga actualizada correctamente.',
        'data' => [
            'id' => $id,
            'usuario_id' => (int) $recarga['usuario_id'],
            'importe' => $importe,
            'fecha' => $fecha,
            'motivo' => $motivo === '' ? null : $motivo,
        ],
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins recarga_update: ' . $e->getMessage());
    api_error(500, 'No se ha podido actualizar la recarga.');
}
