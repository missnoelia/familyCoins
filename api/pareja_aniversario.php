<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['POST']);

$datos = api_datos();
$fecha = null;

if (array_key_exists('fecha_aniversario', $datos)
    && $datos['fecha_aniversario'] !== ''
    && $datos['fecha_aniversario'] !== null
) {
    $fecha = api_fecha(api_texto($datos['fecha_aniversario'], 10));

    if ($fecha === null) {
        api_error(400, 'La fecha de aniversario no es válida.');
    }
}

try {
    $usuario = api_exigir_pareja();
    $db = api_db();
    $coupleModel = new Couple($db);
    $pareja = $coupleModel->actualizarAniversario((int) $usuario['id'], $fecha);

    if ($pareja === null) {
        api_error(404, 'No se ha encontrado la pareja.');
    }

    api_responder(200, [
        'success' => true,
        'message' => 'Fecha de aniversario actualizada correctamente.',
        'data' => $pareja,
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins pareja_aniversario: ' . $e->getMessage());
    api_error(500, 'No se ha podido guardar la fecha de aniversario.');
}
