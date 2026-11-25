<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['POST']);

$datos = api_datos();
$fecha = null;

if (array_key_exists('fecha_nacimiento', $datos)
    && $datos['fecha_nacimiento'] !== ''
    && $datos['fecha_nacimiento'] !== null
) {
    $fecha = api_fecha(api_texto($datos['fecha_nacimiento'], 10));

    if ($fecha === null) {
        api_error(400, 'La fecha de nacimiento no es válida.');
    }
}

try {
    $usuario = api_usuario_actual();
    $db = api_db();
    $userModel = new User($db);

    $userModel->updateBirthday((int) $usuario['id'], $fecha);

    api_responder(200, [
        'success' => true,
        'message' => 'Fecha de nacimiento actualizada correctamente.',
        'data' => $userModel->getById((int) $usuario['id']),
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins perfil_fecha_nacimiento: ' . $e->getMessage());
    api_error(500, 'No se ha podido guardar la fecha de nacimiento.');
}
