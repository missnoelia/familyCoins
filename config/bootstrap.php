<?php
declare(strict_types=1);

/**
 * Arranque común de todas las APIs.
 *
 * Se encarga de la sesión, las cabeceras JSON, la conexión a la base de datos y
 * de las validaciones y respuestas que comparten todos los endpoints.
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

/**
 * Error controlado que se puede lanzar desde cualquier modelo o endpoint.
 */
class ApiException extends RuntimeException
{
    private int $httpStatus;

    public function __construct(string $message, int $httpStatus = 400)
    {
        parent::__construct($message);
        $this->httpStatus = $httpStatus;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}

/**
 * Envía una respuesta JSON y termina la ejecución.
 */
function api_responder(int $codigo, array $respuesta): void
{
    http_response_code($codigo);
    echo json_encode(
        $respuesta,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

/**
 * Envía un error JSON y termina la ejecución.
 */
function api_error(int $codigo, string $mensaje): void
{
    api_responder($codigo, [
        'success' => false,
        'message' => $mensaje,
    ]);
}

/**
 * Comprueba el método HTTP del endpoint actual.
 *
 * @param array<int, string> $permitidos
 */
function api_metodo(array $permitidos): void
{
    $permitidos = array_map('strtoupper', $permitidos);
    $metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    if (!in_array($metodo, $permitidos, true)) {
        header('Allow: ' . implode(', ', $permitidos));
        api_error(405, 'Método no permitido. Usa: ' . implode(', ', $permitidos) . '.');
    }
}

/**
 * Devuelve el cuerpo de la petición como array.
 *
 * Acepta JSON y formularios tradicionales.
 */
function api_datos(): array
{
    $cuerpo = file_get_contents('php://input');
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (is_string($cuerpo) && trim($cuerpo) !== '') {
        $datos = json_decode($cuerpo, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            if (!is_array($datos)) {
                api_error(400, 'El cuerpo de la petición debe ser un objeto JSON.');
            }

            return $datos;
        }

        // Si el cliente ha declarado JSON, el cuerpo debe ser JSON válido.
        if (stripos($contentType, 'application/json') !== false) {
            api_error(400, 'El cuerpo de la petición no es un JSON válido.');
        }
    }

    // Compatibilidad con formularios HTML tradicionales.
    return $_POST;
}

/**
 * Devuelve el ID del usuario autenticado o null.
 */
function api_sesion_id(): ?int
{
    $id = $_SESSION['usuario_id'] ?? null;

    if (is_int($id) && $id > 0) {
        return $id;
    }

    if (is_string($id) && ctype_digit($id) && (int) $id > 0) {
        return (int) $id;
    }

    return null;
}

/**
 * Exige una sesión iniciada y devuelve el ID del usuario.
 */
function api_exigir_sesion(): int
{
    $id = api_sesion_id();

    if ($id === null) {
        api_error(401, 'Debes iniciar sesión.');
    }

    return $id;
}

/**
 * Conexión PDO compartida durante toda la petición.
 */
function api_db(): PDO
{
    static $db = null;

    if ($db instanceof PDO) {
        return $db;
    }

    try {
        $db = (new Database())->connect();
    } catch (Throwable $e) {
        error_log('FamilyCoins DB: ' . $e->getMessage());
        api_error(500, 'No se ha podido conectar con la base de datos.');
    }

    return $db;
}

/**
 * Devuelve el usuario autenticado y activo.
 *
 * Si la cuenta ha sido dada de baja mientras tenía la sesión abierta, se cierra
 * la sesión y se responde con 401.
 *
 * @return array<string, mixed>
 */
function api_usuario_actual(): array
{
    $id = api_exigir_sesion();
    $db = api_db();
    $userModel = new User($db);
    $usuario = $userModel->getById($id);

    if ($usuario === null || !User::esActivo($usuario['activo'] ?? false)) {
        $userModel->logout();
        api_error(401, 'Tu cuenta no está activa.');
    }

    return $usuario;
}

/**
 * Exige sesión y pareja vinculada. Devuelve el usuario autenticado.
 *
 * @return array<string, mixed>
 */
function api_exigir_pareja(): array
{
    $usuario = api_usuario_actual();
    $coupleModel = new Couple(api_db());

    if (!$coupleModel->hasPartner((int) $usuario['id'])) {
        api_error(403, 'Debes vincular tu pareja antes de continuar.');
    }

    return $usuario;
}

/**
 * Normaliza un texto recibido por la API.
 *
 * Si se indica $maximo, se valida que no supere esa longitud.
 */
function api_texto(mixed $valor, int $maximo = 0): string
{
    $texto = is_scalar($valor) ? trim((string) $valor) : '';

    if ($maximo > 0 && mb_strlen($texto) > $maximo) {
        api_error(400, 'Uno de los campos supera la longitud permitida.');
    }

    return $texto;
}

/**
 * Valida un número entero mayor que cero.
 *
 * No acepta decimales, signos ni notación científica.
 */
function api_entero_positivo(mixed $valor): ?int
{
    $maximoPostgres = 2147483647;

    if (is_int($valor)) {
        return ($valor > 0 && $valor <= $maximoPostgres) ? $valor : null;
    }

    if (is_string($valor) && preg_match('/^[0-9]+$/', trim($valor)) === 1) {
        $numero = (int) trim($valor);

        return ($numero > 0 && $numero <= $maximoPostgres) ? $numero : null;
    }

    return null;
}

/**
 * Valida una fecha real en formato Y-m-d.
 */
function api_fecha(mixed $valor): ?string
{
    if (!is_string($valor)) {
        return null;
    }

    $fecha = trim($valor);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
    $errores = DateTimeImmutable::getLastErrors();

    if ($date === false) {
        return null;
    }

    if (is_array($errores) && ($errores['warning_count'] > 0 || $errores['error_count'] > 0)) {
        return null;
    }

    return $date->format('Y-m-d') === $fecha ? $fecha : null;
}

/**
 * Convierte los valores habituales de un booleano a bool.
 */
function api_booleano(mixed $valor): ?bool
{
    if (is_bool($valor)) {
        return $valor;
    }

    if (is_int($valor)) {
        if ($valor === 1) {
            return true;
        }

        if ($valor === 0) {
            return false;
        }

        return null;
    }

    if (is_string($valor)) {
        $normalizado = strtolower(trim($valor));

        if ($normalizado === '1' || $normalizado === 'true') {
            return true;
        }

        if ($normalizado === '0' || $normalizado === 'false') {
            return false;
        }
    }

    return null;
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Couple.php';
require_once __DIR__ . '/../models/Predefined.php';
require_once __DIR__ . '/../models/Event.php';
require_once __DIR__ . '/../models/Recharge.php';

set_exception_handler(static function (Throwable $e): void {
    if ($e instanceof ApiException) {
        api_responder($e->httpStatus(), [
            'success' => false,
            'message' => $e->getMessage(),
        ]);
    }

    error_log('FamilyCoins API: ' . $e->getMessage());

    api_responder(500, [
        'success' => false,
        'message' => 'Error interno del servidor.',
    ]);
});
