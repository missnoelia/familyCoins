<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['POST']);

$datos = api_datos();

$id = api_entero_positivo($datos['id'] ?? null);

if ($id === null) {
    api_error(400, 'El ID de la pareja no es válido.');
}

try {
    api_exigir_admin();
    $db = api_db();
    $coupleModel = new Couple($db);

    if ($coupleModel->adminGet($id) === null) {
        api_error(404, 'La pareja no existe.');
    }

    $coupleModel->adminDelete($id);

    api_responder(200, [
        'success' => true,
        'message' => 'Pareja eliminada correctamente.',
        'data' => [
            'id' => $id,
        ],
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins admin_pareja_delete: ' . $e->getMessage());
    api_error(500, 'No se ha podido eliminar la pareja.');
}
