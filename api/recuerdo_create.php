<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['POST']);

$datos = api_datos();

$titulo = api_texto($datos['titulo'] ?? '', 100);
$fecha = api_fecha(api_texto($datos['fecha'] ?? '', 10));
$nota = api_texto($datos['nota'] ?? '', 255);

if ($titulo === '') {
    api_error(400, 'El título del recuerdo es obligatorio.');
}

if ($fecha === null) {
    api_error(400, 'La fecha del recuerdo no es válida.');
}

if ($nota === '') {
    api_error(400, 'La nota del recuerdo es obligatoria.');
}

try {
    $usuario = api_exigir_pareja();
    $db = api_db();
    $pareja = (new Couple($db))->getParejaDeUsuario((int) $usuario['id']);

    if ($pareja === null) {
        api_error(404, 'No se ha encontrado la pareja.');
    }

    $recuerdo = (new Memory($db))->create(
        (int) $pareja['id'],
        (int) $usuario['id'],
        $titulo,
        $fecha,
        $nota
    );

    api_responder(201, [
        'success' => true,
        'message' => 'Recuerdo guardado correctamente.',
        'data' => $recuerdo,
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins recuerdo_create: ' . $e->getMessage());
    api_error(500, 'No se ha podido guardar el recuerdo.');
}
