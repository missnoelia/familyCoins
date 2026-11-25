<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['POST']);

$datos = api_datos();

$id = api_entero_positivo($datos['id'] ?? null);

if ($id === null) {
    api_error(400, 'El ID del usuario no es válido.');
}

try {
    $admin = api_exigir_admin();
    $db = api_db();
    $userModel = new User($db);

    $actual = $userModel->getById($id);

    if ($actual === null) {
        api_error(404, 'El usuario no existe.');
    }

    $nombre = api_texto($datos['nombre'] ?? $actual['nombre'], 100);
    $email = strtolower(api_texto($datos['email'] ?? $actual['email'], 150));
    $rol = api_rol($datos['rol'] ?? $actual['rol']);
    $activo = api_booleano($datos['activo'] ?? $actual['activo']);
    $saldoInicial = $actual['saldo_inicial'];
    $fechaNacimiento = $actual['fecha_nacimiento'] ?? null;

    if ($nombre === '') {
        api_error(400, 'El nombre es obligatorio.');
    }

    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        api_error(400, 'El email no es válido.');
    }

    if ($activo === null) {
        api_error(400, 'El campo "activo" debe ser true o false.');
    }

    if (array_key_exists('saldo_inicial', $datos)) {
        if ($datos['saldo_inicial'] === '' || $datos['saldo_inicial'] === null) {
            $saldoInicial = null;
        } else {
            $saldoInicial = api_entero_no_negativo($datos['saldo_inicial']);

            if ($saldoInicial === null) {
                api_error(400, 'El saldo inicial debe ser un entero mayor o igual que cero.');
            }
        }
    }

    if (array_key_exists('fecha_nacimiento', $datos)) {
        if ($datos['fecha_nacimiento'] === '' || $datos['fecha_nacimiento'] === null) {
            $fechaNacimiento = null;
        } else {
            $fechaNacimiento = api_fecha(api_texto($datos['fecha_nacimiento'], 10));

            if ($fechaNacimiento === null) {
                api_error(400, 'La fecha de nacimiento no es válida.');
            }
        }
    }

    $esUnoMismo = (int) $admin['id'] === $id;

    if ($esUnoMismo && (!$activo || $rol !== 'superadmin')) {
        api_error(409, 'No puedes desactivar tu propia cuenta ni quitarte el rol de superadministrador.');
    }

    $eraSuperadminActivo = strtolower((string) $actual['rol']) === 'superadmin'
        && User::esActivo($actual['activo'] ?? false);

    $sigueSiendoSuperadminActivo = $rol === 'superadmin' && $activo;

    if ($eraSuperadminActivo
        && !$sigueSiendoSuperadminActivo
        && $userModel->contarSuperadminsActivos() <= 1
    ) {
        api_error(409, 'Debe quedar al menos un superadministrador activo.');
    }

    $password = null;

    if (isset($datos['password']) && $datos['password'] !== '' && $datos['password'] !== null) {
        $password = api_password($datos['password']);
    }

    $userModel->adminUpdate(
        $id,
        $nombre,
        $email,
        $rol,
        $saldoInicial,
        $activo,
        $password,
        $fechaNacimiento
    );

    api_responder(200, [
        'success' => true,
        'message' => 'Usuario actualizado correctamente.',
        'data' => $userModel->getById($id),
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins admin_usuario_update: ' . $e->getMessage());
    api_error(500, 'No se ha podido actualizar el usuario.');
}
