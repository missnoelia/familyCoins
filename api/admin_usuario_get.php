<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['GET']);

$id = api_entero_positivo($_GET['id'] ?? null);

if ($id === null) {
    api_error(400, 'El ID del usuario no es válido.');
}

try {
    api_exigir_admin();
    $db = api_db();
    $usuario = (new User($db))->getById($id);

    if ($usuario === null) {
        api_error(404, 'El usuario no existe.');
    }

    $pareja = (new Couple($db))->getPartner($id);

    api_responder(200, [
        'success' => true,
        'data' => [
            'usuario' => $usuario,
            'pareja' => $pareja,
        ],
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins admin_usuario_get: ' . $e->getMessage());
    api_error(500, 'No se ha podido consultar el usuario.');
}
