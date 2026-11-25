<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['GET']);

try {
    $usuario = api_usuario_actual();
    $usuarioId = (int) $usuario['id'];
    $db = api_db();

    $pareja = (new Couple($db))->getPartner($usuarioId);
    $parejaInfo = (new Couple($db))->getParejaDeUsuario($usuarioId);

    api_responder(200, [
        'success' => true,
        'data' => [
            'usuario' => $usuario,
            'pareja' => $pareja,
            'pareja_info' => $parejaInfo,
        ],
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins perfil: ' . $e->getMessage());
    api_error(500, 'No se ha podido consultar el perfil.');
}
