<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['GET']);

try {
    $usuario = api_usuario_actual();
    $db = api_db();
    $pareja = (new Couple($db))->getPartner((int) $usuario['id']);

    api_responder(200, [
        'success' => true,
        'data' => [
            'pareja' => $pareja,
        ],
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins pareja_estado: ' . $e->getMessage());
    api_error(500, 'No se ha podido consultar el estado de la pareja.');
}
