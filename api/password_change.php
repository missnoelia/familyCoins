<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['POST']);

$datos = api_datos();
$passwordActual = $datos['password_actual'] ?? null;

if (!is_string($passwordActual) || $passwordActual === '') {
    api_error(400, 'Debes indicar tu contraseña actual.');
}

$passwordNueva = api_password($datos['password_nueva'] ?? null, 'nueva contraseña');

if ($passwordActual === $passwordNueva) {
    api_error(400, 'La nueva contraseña debe ser distinta de la actual.');
}

try {
    $usuario = api_usuario_actual();
    $db = api_db();
    $userModel = new User($db);

    if (!$userModel->changePassword((int) $usuario['id'], $passwordActual, $passwordNueva)) {
        api_error(401, 'La contraseña actual no es correcta.');
    }

    api_responder(200, [
        'success' => true,
        'message' => 'Contraseña actualizada correctamente.',
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins password_change: ' . $e->getMessage());
    api_error(500, 'No se ha podido cambiar la contraseña.');
}
