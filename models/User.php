
<?php

class User {
    private PDO $db;

    public function __construct(PDO $db){
        $this->db=$db;

    }

    public function register($nombre, $email, $password) {
        // Registrar un usuario
        if($this->getByEmail($email)!==null){
            return false;
        }
        $passwordHash=password_hash($password, PASSWORD_DEFAULT);
        $codigo=$this->generateCode();

        $rol= "usuario";
        $sql="insert into usuarios (nombre, email, password_hash, codigo, rol) values (:nombre, :email, :password_hash, :codigo, :rol) returning id";

        $stmt=$this->db->prepare($sql);
        $nombre=trim($nombre);
        $email=strtolower((trim($email)));

        $stmt->bindParam(":nombre", $nombre, PDO::PARAM_STR);
        $stmt->bindParam(":email", $email, PDO::PARAM_STR);
        $stmt->bindParam(":password_hash", $passwordHash, PDO::PARAM_STR);
        $stmt->bindParam(":codigo", $codigo, PDO::PARAM_STR);
        $stmt->bindParam(":rol", $rol, PDO::PARAM_STR);
        
        $stmt->execute();
        return (int) $stmt->fetchColumn();

    }

    public function login($email, $password) {

        // Iniciar sesión

        $user=$this->getByEmail($email);
        if($user===null|| !$user["activo"]){
            return false;
        }
        if(!password_verify($password, $user["password_hash"])){
            return false;
        }

        if(session_status()===PHP_SESSION_NONE){
            session_start();
        }

        session_regenerate_id(true);
        $_SESSION["usuario_id"]=$user["id"];

        return $user;

    

    }

    public function logout() {
        // Cerrar sesión

        if(session_status()===PHP_SESSION_NONE){
            session_start();

        }
        $_SESSION=[];
        if(ini_get("session.use_cookies")){
            $params=session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
        return true;
    }

    public function getByEmail($email) {
        // Buscar usuario por email
        $sql="SELECT * from usuarios where email= :email LIMIT 1 ";
        $stmt=$this->db->prepare($sql);
        $email=strtolower(trim($email));
        $stmt->bindParam(":email", $email, PDO::PARAM_STR);
        $stmt->execute();
        $user=$stmt->fetch(PDO::FETCH_ASSOC);
        return $user?: null;
    }

    public function generateCode() {
        // Generar código único de pareja
        $letras= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

        do{
            $codigo="";
            for($i=0; $i<3; $i++){
                $codigo.=$letras[random_int(0, strlen($letras)-1)];

            }
            for($i=0; $i<4;$i++){
                $codigo.=random_int(0,9);

            }

            $sql="select id from usuarios where codigo= :codigo";
            $stmt=$this->db->prepare($sql);
            $stmt->bindParam(":codigo", $codigo, PDO::PARAM_STR);
            $stmt->execute();


        }while($stmt->fetch(PDO::FETCH_ASSOC));

        return $codigo;
    }

    public function getByCode($codigo){
        $sql="select id, nombre, email, codigo, activo from usuarios where codigo = :codigo limit 1";
        $stmt=$this->db->prepare($sql);

        $codigo=strtoupper(trim($codigo));

        $stmt->bindParam(":codigo", $codigo, PDO::PARAM_STR);
        $stmt->execute();

        $user=$stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?:null;
    }
}
?>
