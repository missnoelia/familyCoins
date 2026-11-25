<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['GET']);

try {
    $usuario = api_usuario_actual();
    $db = api_db();
    $coupleModel = new Couple($db);
    $pareja = $coupleModel->getPartner((int) $usuario['id']);
    $parejaInfo = $coupleModel->getParejaDeUsuario((int) $usuario['id']);

    api_responder(200, [
        'success' => true,
        'data' => [
            'pareja' => $pareja,
            'pareja_info' => $parejaInfo,
        ],
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins pareja_estado: ' . $e->getMessage());
    api_error(500, 'No se ha podido consultar el estado de la pareja.');
}
