<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['GET']);

$id = api_sesion_id();

if ($id === null) {
    api_responder(200, [
        'success' => true,
        'data' => [
            'logged' => false,
            'usuario' => null,
            'tiene_pareja' => false,
            'pareja' => null,
        ],
    ]);
}

try {
    $db = api_db();
    $userModel = new User($db);
    $usuario = $userModel->getById($id);

    if ($usuario === null || !User::esActivo($usuario['activo'] ?? false)) {
        $userModel->logout();

        api_responder(200, [
            'success' => true,
            'data' => [
                'logged' => false,
                'usuario' => null,
                'tiene_pareja' => false,
                'pareja' => null,
            ],
        ]);
    }

    $coupleModel = new Couple($db);
    $pareja = $coupleModel->getPartner($id);

    api_responder(200, [
        'success' => true,
        'data' => [
            'logged' => true,
            'usuario' => [
                'id' => (int) $usuario['id'],
                'nombre' => $usuario['nombre'],
                'email' => $usuario['email'],
                'codigo' => $usuario['codigo'],
                'rol' => $usuario['rol'],
                'saldo_inicial' => $usuario['saldo_inicial'],
            ],
            'tiene_pareja' => $pareja !== null,
            'pareja' => $pareja,
        ],
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins check_login: ' . $e->getMessage());
    api_error(500, 'No se ha podido comprobar la sesión.');
}
