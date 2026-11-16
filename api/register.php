<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['POST']);

$datos = api_datos();

$nombre = api_texto($datos['nombre'] ?? '', 100);
$email = strtolower(api_texto($datos['email'] ?? '', 150));
$password = is_string($datos['password'] ?? null) ? $datos['password'] : '';

if ($nombre === '' || $email === '' || $password === '') {
    api_error(400, 'Todos los campos son obligatorios.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    api_error(400, 'El email no es válido.');
}

if (strlen($password) < 4 || !preg_match('/[A-Z]/', $password)) {
    api_error(400, 'La contraseña debe tener al menos 4 caracteres y una mayúscula.');
}

try {
    $db = api_db();
    $userModel = new User($db);

    $id = $userModel->register($nombre, $email, $password);

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
        ],
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins register: ' . $e->getMessage());
    api_error(500, 'No se ha podido registrar el usuario.');
}
