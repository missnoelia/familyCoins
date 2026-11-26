<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['GET']);

$id = api_entero_positivo($_GET['id'] ?? null);

if ($id === null) {
    api_error(400, 'El ID de la pareja no es válido.');
}

try {
    api_exigir_admin();
    $db = api_db();
    $pareja = (new Couple($db))->adminGet($id);

    if ($pareja === null) {
        api_error(404, 'La pareja no existe.');
    }

    api_responder(200, [
        'success' => true,
        'data' => $pareja,
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins admin_pareja_get: ' . $e->getMessage());
    api_error(500, 'No se ha podido consultar la pareja.');
}
