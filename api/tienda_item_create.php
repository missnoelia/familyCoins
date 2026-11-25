<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['POST']);

$datos = api_datos();
$tipo = strtolower(api_texto($datos['tipo'] ?? '', 20));
$nombre = api_texto($datos['nombre'] ?? '', 100);
$descripcion = api_texto($datos['descripcion'] ?? '', 255);
$coste = api_entero_positivo($datos['coste'] ?? null);

if ($coste === null) {
    api_error(400, 'El coste debe ser un entero entre 1 y 100.');
}

try {
    $usuario = api_exigir_pareja();
    $db = api_db();

    $item = (new Shop($db))->crearItem(
        (int) $usuario['id'],
        $tipo,
        $nombre,
        $descripcion === '' ? null : $descripcion,
        $coste
    );

    api_responder(201, [
        'success' => true,
        'message' => 'Artículo personalizado creado correctamente.',
        'data' => $item,
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins tienda_item_create: ' . $e->getMessage());
    api_error(500, 'No se ha podido crear el artículo.');
}
