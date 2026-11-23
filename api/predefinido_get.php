<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['GET']);

$id = api_entero_positivo($_GET['id'] ?? null);

if ($id === null) {
    api_error(400, 'El ID no es válido.');
}

try {
    $usuario = api_exigir_pareja();
    $db = api_db();

    $predefinido = (new Predefined($db))->getById($id, (int) $usuario['id']);

    if ($predefinido === null) {
        api_error(404, 'Predefinido no encontrado.');
    }

    api_responder(200, [
        'success' => true,
        'data' => $predefinido,
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins predefinido_get: ' . $e->getMessage());
    api_error(500, 'No se ha podido consultar el predefinido.');
}
