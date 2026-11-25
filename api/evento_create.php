<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['POST']);

$datos = api_datos();

$usuarioDestino = api_entero_positivo($datos['usuario_id'] ?? null);
$nombre = api_texto($datos['nombre'] ?? '', 100);
$fecha = api_fecha(api_texto($datos['fecha'] ?? '', 10));
$coste = api_entero_positivo($datos['coste'] ?? null);
$efecto = strtolower(api_texto($datos['efecto'] ?? '', 10));

if ($usuarioDestino === null) {
    api_error(400, 'Debes indicar a qué persona pertenece el evento.');
}

if ($nombre === '') {
    api_error(400, 'El nombre del evento es obligatorio.');
}

if ($fecha === null) {
    api_error(400, 'La fecha del evento no es válida.');
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
    $usuarioId = (int) $usuario['id'];
    $eventoModel = new Event($db);

    if (!$eventoModel->pertenecePareja($usuarioId, $usuarioDestino)) {
        api_error(403, 'No puedes registrar eventos para ese usuario.');
    }

    $predefinidoId = null;

    if (isset($datos['predefinido_id']) && $datos['predefinido_id'] !== '' && $datos['predefinido_id'] !== null) {
        $predefinidoId = api_entero_positivo($datos['predefinido_id']);

        if ($predefinidoId === null) {
            api_error(400, 'El predefinido indicado no es válido.');
        }

        if ((new Predefined($db))->getById($predefinidoId, $usuarioId) === null) {
            api_error(404, 'El predefinido no existe o no te pertenece.');
        }
    }

    $id = $eventoModel->create(
        $usuarioDestino,
        $usuarioId,
        $predefinidoId,
        $nombre,
        $fecha,
        $coste,
        $efecto
    );

    api_responder(201, [
        'success' => true,
        'message' => 'Evento registrado correctamente.',
        'data' => [
            'id' => $id,
            'usuario_id' => $usuarioDestino,
            'registrado_por_id' => $usuarioId,
            'predefinido_id' => $predefinidoId,
            'nombre' => $nombre,
            'fecha' => $fecha,
            'coste' => $coste,
            'efecto' => $efecto,
            'origen' => 'manual',
            'anulado' => false,
        ],
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins evento_create: ' . $e->getMessage());
    api_error(500, 'No se ha podido registrar el evento.');
}
