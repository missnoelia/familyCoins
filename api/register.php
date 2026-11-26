<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['POST']);

$datos = api_datos();

$nombre = api_texto($datos['nombre'] ?? '', 100);
$email = strtolower(api_texto($datos['email'] ?? '', 150));
$password = api_password($datos['password'] ?? null);
$fechaNacimiento = null;

if (isset($datos['fecha_nacimiento']) && $datos['fecha_nacimiento'] !== '' && $datos['fecha_nacimiento'] !== null) {
    $fechaNacimiento = api_fecha(api_texto($datos['fecha_nacimiento'], 10));

    if ($fechaNacimiento === null) {
        api_error(400, 'La fecha de nacimiento no es válida.');
    }
}

if ($nombre === '' || $email === '') {
    api_error(400, 'Todos los campos son obligatorios.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    api_error(400, 'El email no es válido.');
}

try {
    $db = api_db();
    $userModel = new User($db);

    $id = $userModel->register($nombre, $email, $password, 'usuario', null, true, $fechaNacimiento);

    if ($id === false) {
        api_error(409, 'El email ya está registrado.');
    }

    $usuario = $userModel->getById($id);

    api_responder(201, [
        'success' => true,
        'message' => 'Usuario registrado correctamente.',
        'data' => [
            'id' => $id,
            'nombre' => $usuario['nombre'] ?? $nombre,
            'email' => $usuario['email'] ?? $email,
            'codigo' => $usuario['codigo'] ?? null,
            'fecha_nacimiento' => $usuario['fecha_nacimiento'] ?? null,
        ],
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins register: ' . $e->getMessage());
    api_error(500, 'No se ha podido registrar el usuario.');
}
