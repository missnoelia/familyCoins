<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['GET']);

/**
 * Respuesta común cuando no hay sesión válida.
 */
$respuestaSinSesion = static function (): array {
    return [
        'success' => true,
        'data' => [
            'logged' => false,
            'usuario' => null,
            'tiene_pareja' => false,
            'pareja' => null,
            'pareja_info' => null,
            'streak' => null,
            'planta' => null,
            'bonos' => [],
        ],
    ];
};

$id = api_sesion_id();

if ($id === null) {
    api_responder(200, $respuestaSinSesion());
}

try {
    $db = api_db();
    $userModel = new User($db);
    $usuario = $userModel->getById($id);

    if ($usuario === null || !User::esActivo($usuario['activo'] ?? false)) {
        $userModel->logout();
        api_responder(200, $respuestaSinSesion());
    }

    $coupleModel = new Couple($db);
    $pareja = $coupleModel->getPartner($id);
    $parejaInfo = $coupleModel->getParejaDeUsuario($id);
    $streak = null;
    $planta = null;
    $bonos = [];

    if ($pareja !== null) {
        try {
            $streak = (new Streak($db))->registrarEntrada($id);
        } catch (Throwable $e) {
            error_log('FamilyCoins check_login racha: ' . $e->getMessage());
        }

        try {
            $planta = (new Plant($db))->getEstado($id);
        } catch (Throwable $e) {
            error_log('FamilyCoins check_login planta: ' . $e->getMessage());
        }

        try {
            $bonos = (new Bonus($db))->procesar($id);
        } catch (Throwable $e) {
            error_log('FamilyCoins check_login bonos: ' . $e->getMessage());
        }
    }

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
                'fecha_nacimiento' => $usuario['fecha_nacimiento'] ?? null,
            ],
            'tiene_pareja' => $pareja !== null,
            'pareja' => $pareja,
            'pareja_info' => $parejaInfo,
            'streak' => $streak,
            'planta' => $planta,
            'bonos' => $bonos,
        ],
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins check_login: ' . $e->getMessage());
    api_error(500, 'No se ha podido comprobar la sesión.');
}
