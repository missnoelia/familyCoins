<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['POST']);

$datos = api_datos();

$usuarioDestino = api_entero_positivo($datos['usuario_id'] ?? null);
$importe = api_entero_positivo($datos['importe'] ?? null);
$fecha = api_fecha(api_texto($datos['fecha'] ?? '', 10));
$motivo = api_texto($datos['motivo'] ?? '', 255);

if ($usuarioDestino === null) {
    api_error(400, 'Debes indicar a qué persona se le suma la recarga.');
}

if ($importe === null) {
    api_error(400, 'El importe debe ser un número entero mayor que cero.');
}

if ($fecha === null) {
    api_error(400, 'La fecha de la recarga no es válida.');
}

try {
    $usuario = api_exigir_pareja();
    $db = api_db();
    $usuarioId = (int) $usuario['id'];

    if (!(new Couple($db))->esDeLaPareja($usuarioId, $usuarioDestino)) {
        api_error(403, 'No puedes realizar recargas para ese usuario.');
    }

    $id = (new Recharge($db))->create(
        $usuarioDestino,
        $importe,
        $fecha,
        $motivo === '' ? null : $motivo
    );

    api_responder(201, [
        'success' => true,
        'message' => 'Recarga creada correctamente.',
        'data' => [
            'id' => $id,
            'usuario_id' => $usuarioDestino,
            'importe' => $importe,
            'fecha' => $fecha,
            'motivo' => $motivo === '' ? null : $motivo,
        ],
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins recarga_create: ' . $e->getMessage());
    api_error(500, 'No se ha podido crear la recarga.');
}
