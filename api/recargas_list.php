<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['GET']);

try {
    $usuario = api_exigir_pareja();
    $db = api_db();

    $recargas = (new Recharge($db))->getAll((int) $usuario['id']);

    api_responder(200, [
        'success' => true,
        'data' => $recargas,
        'meta' => [
            'total' => count($recargas),
        ],
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins recargas_list: ' . $e->getMessage());
    api_error(500, 'No se han podido consultar las recargas.');
}
