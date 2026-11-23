<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['POST']);

$datos = api_datos();
$id = api_entero_positivo($datos['id'] ?? null);

if ($id === null) {
    api_error(400, 'El ID no es válido.');
}

try {
    $usuario = api_exigir_pareja();
    $db = api_db();
    $predefinidoModel = new Predefined($db);
    $usuarioId = (int) $usuario['id'];

    if ($predefinidoModel->getById($id, $usuarioId) === null) {
        api_error(404, 'Predefinido no encontrado.');
    }

    if (!$predefinidoModel->delete($id, $usuarioId)) {
        api_error(404, 'No se ha podido eliminar el predefinido.');
    }

    api_responder(200, [
        'success' => true,
        'message' => 'Predefinido eliminado correctamente.',
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins predefinido_delete: ' . $e->getMessage());
    api_error(500, 'No se ha podido eliminar el predefinido.');
}
