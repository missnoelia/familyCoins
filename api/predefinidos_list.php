<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['GET']);

try {
    $usuario = api_exigir_pareja();
    $db = api_db();

    $predefinidos = (new Predefined($db))->getAll((int) $usuario['id']);

    api_responder(200, [
        'success' => true,
        'data' => $predefinidos,
        'meta' => [
            'total' => count($predefinidos),
        ],
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins predefinidos_list: ' . $e->getMessage());
    api_error(500, 'No se han podido consultar los predefinidos.');
}
