<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['POST']);

try {
    $usuario = api_exigir_pareja();
    $db = api_db();

    $resultado = (new Streak($db))->registrarEntrada((int) $usuario['id']);

    if ($resultado['completado_ahora']) {
        $mensaje = '¡Habéis completado el día! Racha actual: '
            . $resultado['racha_actual'] . '.';
    } elseif (!$resultado['registrada']) {
        $mensaje = 'Ya tenías registrada la entrada de hoy.';
    } elseif ($resultado['completado_hoy']) {
        $mensaje = 'Entrada registrada. El día ya estaba completo.';
    } else {
        $mensaje = 'Entrada registrada. Falta que tu pareja entre hoy.';
    }

    api_responder(200, [
        'success' => true,
        'message' => $mensaje,
        'data' => $resultado,
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins streak_checkin: ' . $e->getMessage());
    api_error(500, 'No se ha podido registrar la entrada.');
}
