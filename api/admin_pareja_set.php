<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['POST']);

$datos = api_datos();

$usuario1 = api_entero_positivo($datos['usuario_1_id'] ?? $datos['usuario1_id'] ?? null);
$usuario2 = api_entero_positivo($datos['usuario_2_id'] ?? $datos['usuario2_id'] ?? null);

if ($usuario1 === null || $usuario2 === null) {
    api_error(400, 'Debes indicar los dos usuarios de la pareja.');
}

try {
    api_exigir_admin();
    $db = api_db();
    $coupleModel = new Couple($db);

    $resultado = $coupleModel->adminSet($usuario1, $usuario2);
    $pareja = $coupleModel->adminGet($resultado['id']);

    api_responder($resultado['creada'] ? 201 : 200, [
        'success' => true,
        'message' => $resultado['creada']
            ? 'Pareja creada correctamente.'
            : 'La pareja ya existía.',
        'data' => $pareja,
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins admin_pareja_set: ' . $e->getMessage());
    api_error(500, 'No se ha podido guardar la pareja.');
}
