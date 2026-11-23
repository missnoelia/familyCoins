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
        api_error(409, 'No se puede modificar un evento anulado.');
    }

    // Los campos no enviados conservan su valor actual.
    $usuarioDestino = array_key_exists('usuario_id', $datos)
        ? api_entero_positivo($datos['usuario_id'])
        : (int) $evento['usuario_id'];

    $nombre = array_key_exists('nombre', $datos)
        ? api_texto($datos['nombre'], 100)
        : (string) $evento['nombre'];

    $fecha = array_key_exists('fecha', $datos)
        ? api_fecha(api_texto($datos['fecha'], 10))
        : (string) $evento['fecha'];

    $coste = array_key_exists('coste', $datos)
        ? api_entero_positivo($datos['coste'])
        : (int) $evento['coste'];

    $efecto = array_key_exists('efecto', $datos)
        ? strtolower(api_texto($datos['efecto'], 10))
        : (string) $evento['efecto'];

    if ($usuarioDestino === null) {
        api_error(400, 'Debes indicar a qué persona pertenece el evento.');
    }

    if (!$eventoModel->pertenecePareja($usuarioId, $usuarioDestino)) {
        api_error(403, 'No puedes asignar el evento a ese usuario.');
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

    $predefinidoId = $evento['predefinido_id'] !== null
        ? (int) $evento['predefinido_id']
        : null;

    if (array_key_exists('predefinido_id', $datos)) {
        if ($datos['predefinido_id'] === '' || $datos['predefinido_id'] === null) {
            $predefinidoId = null;
        } else {
            $predefinidoId = api_entero_positivo($datos['predefinido_id']);

            if ($predefinidoId === null) {
                api_error(400, 'El predefinido indicado no es válido.');
            }

            if ((new Predefined($db))->getById($predefinidoId, $usuarioId) === null) {
                api_error(404, 'El predefinido no existe o no te pertenece.');
            }
        }
    }

    $actualizado = $eventoModel->update(
        $id,
        $usuarioDestino,
        $predefinidoId,
        $nombre,
        $fecha,
        $coste,
        $efecto
    );

    if (!$actualizado) {
        // rowCount puede ser 0 porque los valores no han cambiado. Se
        // comprueba que el evento siga existiendo y no se haya anulado a la vez.
        $comprobacion = $eventoModel->getById($id);

        if ($comprobacion === null || Event::esAnulado($comprobacion['anulado'] ?? false)) {
            api_error(409, 'El evento ha sido anulado o eliminado mientras se actualizaba.');
        }
    }

    api_responder(200, [
        'success' => true,
        'message' => 'Evento actualizado correctamente.',
        'data' => [
            'id' => $id,
            'usuario_id' => $usuarioDestino,
            'predefinido_id' => $predefinidoId,
            'nombre' => $nombre,
            'fecha' => $fecha,
            'coste' => $coste,
            'efecto' => $efecto,
            'anulado' => false,
        ],
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins evento_update: ' . $e->getMessage());
    api_error(500, 'No se ha podido actualizar el evento.');
}
