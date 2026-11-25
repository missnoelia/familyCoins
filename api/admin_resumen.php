<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['GET']);

try {
    api_exigir_admin();
    $db = api_db();

    $usuarios = $db->query(
        'SELECT COUNT(*) AS total,
                COUNT(*) FILTER (WHERE activo = TRUE) AS activos,
                COUNT(*) FILTER (WHERE rol = \'superadmin\' AND activo = TRUE) AS superadmins_activos
         FROM usuarios'
    )->fetch(PDO::FETCH_ASSOC);

    $parejas = $db->query('SELECT COUNT(*) FROM parejas')->fetchColumn();

    $eventos = $db->query(
        'SELECT COUNT(*) AS total,
                COUNT(*) FILTER (WHERE anulado = FALSE) AS activos,
                COUNT(*) FILTER (WHERE anulado = TRUE) AS anulados,
                COALESCE(SUM(coste) FILTER (WHERE anulado = FALSE AND efecto = \'resta\'), 0) AS total_resta,
                COALESCE(SUM(coste) FILTER (WHERE anulado = FALSE AND efecto = \'suma\'), 0) AS total_suma
         FROM eventos'
    )->fetch(PDO::FETCH_ASSOC);

    $recargas = $db->query(
        'SELECT COUNT(*) AS total,
                COALESCE(SUM(importe), 0) AS importe_total
         FROM recargas'
    )->fetch(PDO::FETCH_ASSOC);

    $saldoInicial = $db->query(
        'SELECT COALESCE(SUM(saldo_inicial), 0) FROM usuarios'
    )->fetchColumn();

    $saldoGlobal = (int) $saldoInicial
        + (int) $recargas['importe_total']
        + (int) $eventos['total_suma']
        - (int) $eventos['total_resta'];

    api_responder(200, [
        'success' => true,
        'data' => [
            'usuarios' => [
                'total' => (int) $usuarios['total'],
                'activos' => (int) $usuarios['activos'],
                'superadmins_activos' => (int) $usuarios['superadmins_activos'],
            ],
            'parejas' => [
                'total' => (int) $parejas,
            ],
            'eventos' => [
                'total' => (int) $eventos['total'],
                'activos' => (int) $eventos['activos'],
                'anulados' => (int) $eventos['anulados'],
                'total_resta' => (int) $eventos['total_resta'],
                'total_suma' => (int) $eventos['total_suma'],
            ],
            'recargas' => [
                'total' => (int) $recargas['total'],
                'importe_total' => (int) $recargas['importe_total'],
            ],
            'saldo_global' => $saldoGlobal,
        ],
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins admin_resumen: ' . $e->getMessage());
    api_error(500, 'No se ha podido generar el resumen.');
}
