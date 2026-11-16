
<?php

header("Content-Type: application/json; charset=UTF-8");

require_once "../config/database.php";
require_once "../models/User.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$data = json_decode(file_get_contents("php://input"), true);

if (!is_array($data)) {
    $data = $_POST;
}

$email = trim($data["email"] ?? "");
$password = $data["password"] ?? "";

if ($email === "" || $password === "") {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Introduce el email y la contraseña."
    ]);
    exit;
}

try {
    $userModel = new User($db);

    $user = $userModel->login($email, $password);

    if ($user === false) {
        http_response_code(401);
        echo json_encode([
            "success" => false,
            "message" => "Email o contraseña incorrectos."
        ]);
        exit;
    }

    echo json_encode([
        "success" => true,
        "message" => "Sesión iniciada correctamente.",
        "data" => [
            "id" => $user["id"],
            "nombre" => $user["nombre"],
            "email" => $user["email"]
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error al iniciar sesión."
    ]);
}
