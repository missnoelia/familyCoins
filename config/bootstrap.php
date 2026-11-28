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
header('Cache-Control: no-store');

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
 * Comprueba que las peticiones POST de un navegador vengan del mismo origen.
 *
 * Es una defensa adicional contra CSRF. Los clientes que no envían cabecera
 * Origin (curl, pruebas, algunas integraciones) siguen permitidos.
 */
function api_exigir_origen(): void
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if ($origin === '') {
        return;
    }

    $host = $_SERVER['HTTP_HOST'] ?? '';

    if ($host === '') {
        api_error(403, 'Origen no permitido.');
    }

    $partesHost = parse_url('http://' . $host);
    $partesOrigen = parse_url($origin);

    if (!is_array($partesHost) || !is_array($partesOrigen)) {
        api_error(403, 'Origen no permitido.');
    }

    $hostEsperado = strtolower((string) ($partesHost['host'] ?? ''));
    $hostOrigen = strtolower((string) ($partesOrigen['host'] ?? ''));

    if ($hostEsperado === '' || $hostOrigen === '' || $hostEsperado !== $hostOrigen) {
        api_error(403, 'Origen no permitido.');
    }

    $esHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $puertoEsperado = $partesHost['port'] ?? ($esHttps ? 443 : 80);
    $puertoOrigen = $partesOrigen['port'] ?? ($esHttps ? 443 : 80);

    if ((int) $puertoEsperado !== (int) $puertoOrigen) {
        api_error(403, 'Origen no permitido.');
    }
}

/**
 * Comprueba el método HTTP del endpoint actual.
 *
 * @param array<int, string> $permitidos
 */
function api_metodo(array $permitidos): void
{
    api_exigir_origen();

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
 * Exige sesión y rol de superadministrador.
 *
 * @return array<string, mixed>
 */
function api_exigir_admin(): array
{
    $usuario = api_usuario_actual();

    if (strtolower((string) ($usuario['rol'] ?? '')) !== 'superadmin') {
        api_error(403, 'Necesitas permisos de superadministrador.');
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
 * Valida un número entero mayor o igual que cero.
 *
 * Se usa para importes y saldos, que sí admiten el valor 0.
 */
function api_entero_no_negativo(mixed $valor): ?int
{
    if ($valor === null || $valor === '') {
        return null;
    }

    $maximoPostgres = 2147483647;

    if (is_int($valor)) {
        return ($valor >= 0 && $valor <= $maximoPostgres) ? $valor : null;
    }

    if (is_string($valor) && preg_match('/^[0-9]+$/', trim($valor)) === 1) {
        $numero = (int) trim($valor);

        return ($numero >= 0 && $numero <= $maximoPostgres) ? $numero : null;
    }

    return null;
}

/**
 * Valida el rol de un usuario.
 */
function api_rol(mixed $valor, string $defecto = 'usuario'): string
{
    $rol = is_scalar($valor) ? strtolower(trim((string) $valor)) : '';

    if ($rol === '') {
        return $defecto;
    }

    if (!in_array($rol, ['usuario', 'superadmin'], true)) {
        api_error(400, 'El rol debe ser "usuario" o "superadmin".');
    }

    return $rol;
}

/**
 * Valida una contraseña.
 *
 * La política se mantiene igual que en el registro (mínimo 4 caracteres y una
 * mayúscula) y se añade el límite de 72 bytes que impone bcrypt.
 */
function api_password(mixed $valor, string $nombreCampo = 'contraseña'): string
{
    if (!is_string($valor)) {
        api_error(400, 'La ' . $nombreCampo . ' no es válida.');
    }

    $password = (string) $valor;

    if (mb_strlen($password) < 4) {
        api_error(400, 'La ' . $nombreCampo . ' debe tener al menos 4 caracteres.');
    }

    if (strlen($password) > 72) {
        api_error(400, 'La ' . $nombreCampo . ' no puede superar los 72 bytes.');
    }

    if (preg_match('/[A-Z]/', $password) !== 1) {
        api_error(400, 'La ' . $nombreCampo . ' debe incluir al menos una mayúscula.');
    }

    return $password;
}

/**
 * Lee y valida los parámetros de paginación.
 *
 * @return array{limite: int, offset: int, pagina: int}
 */
function api_paginacion(int $porDefecto = 50, int $maximo = 200): array
{
    $pagina = api_entero_positivo($_GET['pagina'] ?? null) ?? 1;
    $limite = api_entero_positivo($_GET['limite'] ?? null) ?? $porDefecto;

    if ($limite > $maximo) {
        api_error(400, 'El límite no puede ser mayor que ' . $maximo . '.');
    }

    return [
        'limite' => $limite,
        'offset' => ($pagina - 1) * $limite,
        'pagina' => $pagina,
    ];
}

/**
 * Construye la respuesta paginada estándar.
 *
 * @param array<int, mixed> $datos
 * @param array{limite: int, offset: int, pagina: int} $paginacion
 * @return array<string, mixed>
 */
function api_paginado(array $datos, int $total, array $paginacion): array
{
    return [
        'success' => true,
        'data' => $datos,
        'total' => $total,
        'pagina' => $paginacion['pagina'],
        'limite' => $paginacion['limite'],
        'paginas' => $total === 0 ? 0 : (int) ceil($total / $paginacion['limite']),
    ];
}

/**
 * Zona horaria compartida por rachas, plantas y bonos.
 */
function api_zona_horaria(): DateTimeZone
{
    static $zona = null;

    if ($zona instanceof DateTimeZone) {
        return $zona;
    }

    $nombreZona = getenv('STREAK_TIMEZONE');

    if ($nombreZona === false || trim($nombreZona) === '') {
        $nombreZona = 'Europe/Madrid';
    }

    try {
        $zona = new DateTimeZone($nombreZona);
    } catch (Throwable $e) {
        $zona = new DateTimeZone('Europe/Madrid');
    }

    return $zona;
}

/**
 * Fecha de hoy en la zona horaria de la aplicación.
 */
function api_hoy(): DateTimeImmutable
{
    return new DateTimeImmutable('now', api_zona_horaria());
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
require_once __DIR__ . '/../models/Balance.php';
require_once __DIR__ . '/../models/Stats.php';
require_once __DIR__ . '/../models/Streak.php';
require_once __DIR__ . '/../models/Plant.php';
require_once __DIR__ . '/../models/Shop.php';
require_once __DIR__ . '/../models/Bonus.php';
require_once __DIR__ . '/../models/Memory.php';
require_once __DIR__ . '/../models/Achievement.php';

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
