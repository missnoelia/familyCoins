
<?php

header("Content-Type: application/json; charset=UTF-8");

session_start();

require_once "../config/database.php";
require_once "../models/User.php";

if (!isset($_SESSION["usuario_id"])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Debes iniciar sesión."
    ]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!is_array($data)) {
    $data = $_POST;
}

$codigo = strtoupper(trim($data["codigo"] ?? ""));
$miId = $_SESSION["usuario_id"];

if ($codigo === "") {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Introduce el código de la otra persona."
    ]);
    exit;
}

try {
    $userModel = new User($db);

    // Buscar al usuario que tiene ese código
    $otroUsuario = $userModel->getByCode($codigo);

    if ($otroUsuario === null || !$otroUsuario["activo"]) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "El código no existe o el usuario no está activo."
        ]);
        exit;
    }

    $otroId = (int) $otroUsuario["id"];

    // No permitir vincularse consigo mismo
    if ($miId == $otroId) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "No puedes vincularte contigo mismo."
        ]);
        exit;
    }

    $db->beginTransaction();

    // Comprobar si alguno de los dos ya tiene pareja
    $sql = "SELECT id FROM parejas
            WHERE usuario_1_id IN (:miId1, :otroId1)
            OR usuario_2_id IN (:miId2, :otroId2)
            LIMIT 1";

    $stmt = $db->prepare($sql);
    $stmt->bindParam(":miId1", $miId, PDO::PARAM_INT);
    $stmt->bindParam(":otroId1", $otroId, PDO::PARAM_INT);
    $stmt->bindParam(":miId2", $miId, PDO::PARAM_INT);
    $stmt->bindParam(":otroId2", $otroId, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        $db->rollBack();

        http_response_code(409);
        echo json_encode([
            "success" => false,
            "message" => "Uno de los usuarios ya tiene pareja."
        ]);
        exit;
    }

    // Crear la pareja
    $sql = "INSERT INTO parejas (usuario_1_id, usuario_2_id)
            VALUES (:usuario1, :usuario2)
            RETURNING id";

    $stmt = $db->prepare($sql);
    $stmt->bindParam(":usuario1", $miId, PDO::PARAM_INT);
    $stmt->bindParam(":usuario2", $otroId, PDO::PARAM_INT);
    $stmt->execute();

    $parejaId = $stmt->fetchColumn();

    $db->commit();

    echo json_encode([
        "success" => true,
        "message" => "Pareja vinculada correctamente.",
        "data" => [
            "pareja_id" => $parejaId,
            "usuario" => [
                "id" => $otroUsuario["id"],
                "nombre" => $otroUsuario["nombre"],
                "codigo" => $otroUsuario["codigo"]
            ]
        ]
    ]);

} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "No se ha podido vincular la pareja."
    ]);
}
