<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['POST']);

$datos = api_datos();
$codigo = strtoupper(api_texto($datos['codigo'] ?? ''));

if (!preg_match('/^[A-Z]{3}[0-9]{4}$/', $codigo)) {
    api_error(400, 'El código debe tener 3 letras y 4 dígitos (por ejemplo, ABC1234).');
}

try {
    $usuario = api_usuario_actual();
    $db = api_db();
    $userModel = new User($db);

    $otroUsuario = $userModel->getByCode($codigo);

    if ($otroUsuario === null || !User::esActivo($otroUsuario['activo'] ?? false)) {
        api_error(404, 'El código no existe o el usuario no está activo.');
    }

    $miId = (int) $usuario['id'];
    $otroId = (int) $otroUsuario['id'];

    if ($miId === $otroId) {
        api_error(400, 'No puedes vincularte contigo mismo.');
    }

    $parejaId = (new Couple($db))->link($miId, $otroId);
    $parejaInfo = (new Couple($db))->getParejaDeUsuario($miId);

    api_responder(200, [
        'success' => true,
        'message' => 'Pareja vinculada correctamente.',
        'data' => [
            'pareja_id' => $parejaId,
            'fecha_aniversario' => $parejaInfo['fecha_aniversario'] ?? null,
            'usuario' => [
                'id' => $otroId,
                'nombre' => $otroUsuario['nombre'],
                'codigo' => $otroUsuario['codigo'],
            ],
        ],
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins pareja_vincular: ' . $e->getMessage());
    api_error(500, 'No se ha podido vincular la pareja.');
}
