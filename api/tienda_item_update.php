<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['POST']);

$datos = api_datos();
$id = api_entero_positivo($datos['id'] ?? null);
$tipo = strtolower(api_texto($datos['tipo'] ?? '', 20));
$nombre = api_texto($datos['nombre'] ?? '', 100);
$descripcion = api_texto($datos['descripcion'] ?? '', 255);
$coste = api_entero_positivo($datos['coste'] ?? null);

if ($id === null) {
    api_error(400, 'El ID del artículo no es válido.');
}

if ($coste === null) {
    api_error(400, 'El coste debe ser un entero entre 1 y 100.');
}

try {
    $usuario = api_exigir_pareja();
    $db = api_db();

    $item = (new Shop($db))->actualizarItem(
        (int) $usuario['id'],
        $id,
        $tipo,
        $nombre,
        $descripcion === '' ? null : $descripcion,
        $coste
    );

    api_responder(200, [
        'success' => true,
        'message' => 'Artículo actualizado correctamente.',
        'data' => $item,
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins tienda_item_update: ' . $e->getMessage());
    api_error(500, 'No se ha podido actualizar el artículo.');
}
