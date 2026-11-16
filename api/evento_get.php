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
    $eventoModel = new Event($db);

    $evento = $eventoModel->getById($id);

    if ($evento === null || !$eventoModel->pertenecePareja((int) $usuario['id'], (int) $evento['usuario_id'])) {
        api_error(404, 'Evento no encontrado.');
    }

    api_responder(200, [
        'success' => true,
        'data' => $evento,
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins evento_get: ' . $e->getMessage());
    api_error(500, 'No se ha podido consultar el evento.');
}
