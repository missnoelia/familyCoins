<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['POST']);

$datos = api_datos();
$id = api_entero_positivo($datos['id'] ?? null);

if ($id === null) {
    api_error(400, 'El ID de la recarga no es válido.');
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

    if (!$rechargeModel->delete($id)) {
        api_error(404, 'No se ha podido eliminar la recarga.');
    }

    api_responder(200, [
        'success' => true,
        'message' => 'Recarga eliminada correctamente.',
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins recarga_delete: ' . $e->getMessage());
    api_error(500, 'No se ha podido eliminar la recarga.');
}
