<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['POST']);

$datos = api_datos();
$id = api_entero_positivo($datos['id'] ?? null);

if ($id === null) {
    api_error(400, 'El ID del artículo no es válido.');
}

try {
    $usuario = api_exigir_pareja();
    $db = api_db();

    $item = (new Shop($db))->desactivarItem((int) $usuario['id'], $id);

    api_responder(200, [
        'success' => true,
        'message' => 'Artículo desactivado correctamente.',
        'data' => $item,
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins tienda_item_delete: ' . $e->getMessage());
    api_error(500, 'No se ha podido desactivar el artículo.');
}
