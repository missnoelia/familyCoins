
<?php

header("Content-Type: application/json; charset=UTF-8");

session_start();

require_once "../config/database.php";

if (!isset($_SESSION["usuario_id"])) {
    echo json_encode([
        "success" => true,
        "data" => [
            "logged" => false,
            "usuario" => null,
            "tiene_pareja" => false
        ]
    ]);
    exit;
}

$id = $_SESSION["usuario_id"];

$sql = "SELECT id, nombre
        FROM usuarios
        WHERE id = :id AND activo = TRUE";

$stmt = $db->prepare($sql);
$stmt->bindParam(":id", $id, PDO::PARAM_INT);
$stmt->execute();

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $_SESSION = [];
    session_destroy();

    echo json_encode([
        "success" => true,
        "data" => [
            "logged" => false,
            "usuario" => null,
            "tiene_pareja" => false
        ]
    ]);
    exit;
}

$sql = "SELECT id
        FROM parejas
        WHERE usuario_1_id = :id1
        OR usuario_2_id = :id2
        LIMIT 1";

$stmt = $db->prepare($sql);
$stmt->bindParam(":id1", $id, PDO::PARAM_INT);
$stmt->bindParam(":id2", $id, PDO::PARAM_INT);
$stmt->execute();

$tienePareja = $stmt->fetch(PDO::FETCH_ASSOC) !== false;

echo json_encode([
    "success" => true,
    "data" => [
        "logged" => true,
        "usuario" => [
            "id" => $user["id"],
            "nombre" => $user["nombre"]
        ],
        "tiene_pareja" => $tienePareja
    ]
]);
