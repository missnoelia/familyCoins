<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['POST']);

$datos = api_datos();

$id = api_entero_positivo($datos['id'] ?? null);
$nombre = api_texto($datos['nombre'] ?? '', 100);
$coste = api_entero_positivo($datos['coste'] ?? null);
$efecto = strtolower(api_texto($datos['efecto'] ?? '', 10));

if ($id === null) {
    api_error(400, 'El ID no es válido.');
}

if ($nombre === '') {
    api_error(400, 'El nombre es obligatorio.');
}

if ($coste === null) {
    api_error(400, 'El coste debe ser un número entero mayor que cero.');
}

if (!in_array($efecto, ['resta', 'suma'], true)) {
    api_error(400, 'El efecto debe ser "resta" o "suma".');
}

try {
    $usuario = api_exigir_pareja();
    $db = api_db();
    $predefinidoModel = new Predefined($db);
    $usuarioId = (int) $usuario['id'];

    if ($predefinidoModel->getById($id, $usuarioId) === null) {
        api_error(404, 'Predefinido no encontrado.');
    }

    if ($predefinidoModel->nombreExiste($usuarioId, $nombre, $id)) {
        api_error(409, 'Ya existe otro predefinido con ese nombre.');
    }

    $actualizado = $predefinidoModel->update($id, $usuarioId, $nombre, $coste, $efecto);

    if (!$actualizado && $predefinidoModel->getById($id, $usuarioId) === null) {
        api_error(404, 'El predefinido ha sido eliminado mientras se actualizaba.');
    }

    api_responder(200, [
        'success' => true,
        'message' => 'Predefinido actualizado correctamente.',
        'data' => [
            'id' => $id,
            'usuario_id' => $usuarioId,
            'nombre' => $nombre,
            'coste' => $coste,
            'efecto' => $efecto,
        ],
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins predefinido_update: ' . $e->getMessage());
    api_error(500, 'No se ha podido actualizar el predefinido.');
}
