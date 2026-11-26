<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['POST']);

$datos = api_datos();
$plantaId = strtolower(api_texto($datos['planta_id'] ?? '', 40));

if ($plantaId === '') {
    api_error(400, 'Debes indicar la planta que quieres elegir.');
}

try {
    $usuario = api_exigir_pareja();
    $db = api_db();

    $resultado = (new Plant($db))->elegir((int) $usuario['id'], $plantaId);
    $coste = $resultado['coste'];

    if ($coste > 0) {
        $mensaje = 'Planta cambiada por ' . $coste . ' coins. La etapa y el progreso se conservan.';
    } else {
        $mensaje = 'Planta elegida correctamente.';
    }

    api_responder(200, [
        'success' => true,
        'message' => $mensaje,
        'data' => $resultado['estado'],
        'coste' => $coste,
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins planta_elegir: ' . $e->getMessage());
    api_error(500, 'No se ha podido guardar la planta.');
}
