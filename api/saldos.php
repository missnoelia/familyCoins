<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['GET']);

try {
    $usuario = api_exigir_pareja();
    $db = api_db();
    $usuarioId = (int) $usuario['id'];

    $balanceModel = new Balance($db);
    $resultado = $balanceModel->getSaldos($usuarioId);

    if (isset($_GET['usuario_id']) && $_GET['usuario_id'] !== '') {
        $filtroUsuario = api_entero_positivo($_GET['usuario_id']);

        if ($filtroUsuario === null) {
            api_error(400, 'El usuario del filtro no es válido.');
        }

        if (!(new Event($db))->pertenecePareja($usuarioId, $filtroUsuario)) {
            api_error(403, 'No puedes consultar el saldo de ese usuario.');
        }

        $resultado['usuarios'] = array_values(array_filter(
            $resultado['usuarios'],
            static fn (array $saldo): bool => (int) $saldo['usuario_id'] === $filtroUsuario
        ));
    }

    api_responder(200, [
        'success' => true,
        'data' => $resultado,
    ]);
} catch (PDOException $e) {
    error_log('FamilyCoins saldos: ' . $e->getMessage());
    api_error(500, 'No se han podido calcular los saldos.');
}
