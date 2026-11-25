<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['GET']);

try {
    $db = (new Database())->connect();
    $db->query('SELECT 1')->fetchColumn();

    api_responder(200, [
        'success' => true,
        'data' => [
            'estado' => 'ok',
            'base_de_datos' => 'ok',
            'hora' => (new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM),
        ],
    ]);
} catch (Throwable $e) {
    error_log('FamilyCoins health: ' . $e->getMessage());

    api_responder(503, [
        'success' => false,
        'message' => 'El servicio no está disponible.',
        'data' => [
            'estado' => 'error',
            'base_de_datos' => 'error',
        ],
    ]);
}
