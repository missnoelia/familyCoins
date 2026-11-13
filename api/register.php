
<?php

header("Content-Type: application/json; charset=UTF-8");

require_once "../config/database.php";
require_once "../models/User.php";

$userModel = new User($db);

$data=json_decode(file_get_contents("php://input"), true);

if(!is_array($data)){
    $data=$_POST;
}

$nombre=trim($data["nombre"] ?? "");
$email=trim($data["email"] ?? "");
$password=$data["password"] ?? "";

if($nombre==="" || $email==="" || $password===""){
    http_response_code(400);
    echo json_encode([
        "success"=>false,
        "message"=>"Todos los campos son obligatorios."
    ]);
    exit;
}

if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
    http_response_code(400);
    echo json_encode([
        "success"=>false,
        "message"=>"El email no es válido."
    ]);
    exit;
}

if(strlen($password)<4 || !preg_match('/[A-Z]/', $password)){
    http_response_code(400);
    echo json_encode([
        "success"=>false,
        "message"=>"La contraseña debe tener al menos 4 caracteres y una mayúscula."
    ]);
    exit;
}

try{

    $id=$userModel->register($nombre, $email, $password);

    if($id===false){
        http_response_code(409);
        echo json_encode([
            "success"=>false,
            "message"=>"El email ya está registrado."
        ]);
        exit;
    }

    $user=$userModel->getByEmail($email);

    http_response_code(201);
    echo json_encode([
        "success"=>true,
        "message"=>"Usuario registrado correctamente.",
        "data"=>[
            "id"=>$id,
            "nombre"=>$user["nombre"],
            "email"=>$user["email"],
            "codigo"=>$user["codigo"]
        ]
    ]);

}catch(PDOException $e){

    http_response_code(500);
    echo json_encode([
        "success"=>false,
        "message"=>"No se ha podido registrar el usuario."
    ]);
}

?>
