<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['POST']);

$datos = api_datos();
$id = api_entero_positivo($datos['id'] ?? null);

if ($id === null) {
    api_error(400, 'El ID del evento no es válido.');
}

try {
    $usuario = api_exigir_pareja();
    $db = api_db();
    $usuarioId = (int) $usuario['id'];
    $eventoModel = new Event($db);

    $evento = $eventoModel->getById($id);

    if ($evento === null || !$eventoModel->pertenecePareja($usuarioId, (int) $evento['usuario_id'])) {
        api_error(404, 'Evento no encontrado.');
    }

    if (Event::esAnulado($evento['anulado'] ?? false)) {
        api_error(409, 'El evento ya está anulado.');
    }

    if (!$eventoModel->anular($id)) {
        api_error(409, 'No se ha podido anular el evento.');
    }

    api_responder(200, [
        'success' => true,
        'message' => 'Evento anulado correctamente.',
        'data' => [
            'id' => $id,
            'anulado' => true,
        ],
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins evento_anular: ' . $e->getMessage());
    api_error(500, 'No se ha podido anular el evento.');
}
