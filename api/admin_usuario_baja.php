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

    if ((int) $admin['id'] === $id) {
        api_error(409, 'No puedes darte de baja a ti mismo.');
    }

    $usuario = $userModel->getById($id);

    if ($usuario === null) {
        api_error(404, 'El usuario no existe.');
    }

    if (!User::esActivo($usuario['activo'] ?? false)) {
        api_error(409, 'El usuario ya estaba dado de baja.');
    }

    if (strtolower((string) $usuario['rol']) === 'superadmin'
        && $userModel->contarSuperadminsActivos() <= 1
    ) {
        api_error(409, 'Debe quedar al menos un superadministrador activo.');
    }

    $userModel->adminDeactivate($id);

    api_responder(200, [
        'success' => true,
        'message' => 'Usuario dado de baja correctamente.',
        'data' => [
            'id' => $id,
            'activo' => false,
        ],
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins admin_usuario_baja: ' . $e->getMessage());
    api_error(500, 'No se ha podido dar de baja al usuario.');
}
