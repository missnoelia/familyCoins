<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['GET']);

try {
    $usuario = api_exigir_pareja();
    $db = api_db();

    $estado = (new Streak($db))->getEstado((int) $usuario['id']);

    api_responder(200, [
        'success' => true,
        'data' => $estado,
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins streak: ' . $e->getMessage());
    api_error(500, 'No se ha podido consultar la racha.');
}
