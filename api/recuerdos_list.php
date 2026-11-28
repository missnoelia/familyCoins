<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['GET']);

try {
    $usuario = api_exigir_pareja();
    $db = api_db();
    $pareja = (new Couple($db))->getParejaDeUsuario((int) $usuario['id']);

    if ($pareja === null) {
        api_error(404, 'No se ha encontrado la pareja.');
    }

    $recuerdos = (new Memory($db))->getAll((int) $pareja['id']);

    api_responder(200, [
        'success' => true,
        'data' => $recuerdos,
        'meta' => [
            'total' => count($recuerdos),
        ],
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins recuerdos_list: ' . $e->getMessage());
    api_error(500, 'No se han podido consultar los recuerdos.');
}
