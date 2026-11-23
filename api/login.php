<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['POST']);

$datos = api_datos();

$email = strtolower(api_texto($datos['email'] ?? '', 150));
$password = is_string($datos['password'] ?? null) ? $datos['password'] : '';

if ($email === '' || $password === '') {
    api_error(400, 'Introduce el email y la contraseña.');
}

try {
    $db = api_db();
    $userModel = new User($db);

    $usuario = $userModel->login($email, $password);

    if ($usuario === false) {
        api_error(401, 'Email o contraseña incorrectos.');
    }

    $coupleModel = new Couple($db);
    $pareja = $coupleModel->getPartner((int) $usuario['id']);

    api_responder(200, [
        'success' => true,
        'message' => 'Sesión iniciada correctamente.',
        'data' => [
            'id' => (int) $usuario['id'],
            'nombre' => $usuario['nombre'],
            'email' => $usuario['email'],
            'codigo' => $usuario['codigo'],
            'rol' => $usuario['rol'],
            'tiene_pareja' => $pareja !== null,
            'pareja' => $pareja,
        ],
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins login: ' . $e->getMessage());
    api_error(500, 'Error al iniciar sesión.');
}
