<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['GET']);

try {
    $usuario = api_exigir_pareja();
    $db = api_db();

    $tienda = (new Shop($db))->getTienda((int) $usuario['id']);

    api_responder(200, [
        'success' => true,
        'data' => $tienda,
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins tienda: ' . $e->getMessage());
    api_error(500, 'No se ha podido consultar la tienda.');
}
