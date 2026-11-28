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

    $logros = (new Achievement($db))->getLogros((int) $pareja['id']);

    $desbloqueados = count(array_filter(
        $logros,
        static fn (array $logro): bool => $logro['desbloqueado'] === true
    ));

    api_responder(200, [
        'success' => true,
        'data' => $logros,
        'meta' => [
            'total' => count($logros),
            'desbloqueados' => $desbloqueados,
        ],
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins logros: ' . $e->getMessage());
    api_error(500, 'No se han podido consultar los logros.');
}
