<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

api_metodo(['POST']);

User::cerrarSesion();

api_responder(200, [
    'success' => true,
    'message' => 'Sesión cerrada correctamente.',
]);
