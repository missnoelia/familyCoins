<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['POST']);

$datos = api_datos();

$nombre = api_texto($datos['nombre'] ?? '', 100);
$email = strtolower(api_texto($datos['email'] ?? '', 150));
$password = api_password($datos['password'] ?? null);
$rol = api_rol($datos['rol'] ?? 'usuario');
$activo = api_booleano($datos['activo'] ?? true);
$saldoInicial = null;
$fechaNacimiento = null;

if ($nombre === '') {
    api_error(400, 'El nombre es obligatorio.');
}

if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    api_error(400, 'El email no es válido.');
}

if ($activo === null) {
    api_error(400, 'El campo "activo" debe ser true o false.');
}

if (isset($datos['saldo_inicial']) && $datos['saldo_inicial'] !== '' && $datos['saldo_inicial'] !== null) {
    $saldoInicial = api_entero_no_negativo($datos['saldo_inicial']);

    if ($saldoInicial === null) {
        api_error(400, 'El saldo inicial debe ser un entero mayor o igual que cero.');
    }
}

if (isset($datos['fecha_nacimiento']) && $datos['fecha_nacimiento'] !== '' && $datos['fecha_nacimiento'] !== null) {
    $fechaNacimiento = api_fecha(api_texto($datos['fecha_nacimiento'], 10));

    if ($fechaNacimiento === null) {
        api_error(400, 'La fecha de nacimiento no es válida.');
    }
}

try {
    api_exigir_admin();
    $db = api_db();
    $userModel = new User($db);

    $id = $userModel->register($nombre, $email, $password, $rol, $saldoInicial, $activo, $fechaNacimiento);

    if ($id === false) {
        api_error(409, 'El email ya está registrado.');
    }

    api_responder(201, [
        'success' => true,
        'message' => 'Usuario creado correctamente.',
        'data' => $userModel->getById($id),
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins admin_usuario_create: ' . $e->getMessage());
    api_error(500, 'No se ha podido crear el usuario.');
}
