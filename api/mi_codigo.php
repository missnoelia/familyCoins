<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['GET']);

try {
    $usuario = api_usuario_actual();
    $db = api_db();

    api_responder(200, [
        'success' => true,
        'data' => [
            'codigo' => $usuario['codigo'],
        ],
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins mi_codigo: ' . $e->getMessage());
    api_error(500, 'No se ha podido consultar el código.');
}
