<?php
declare(strict_types=1);

/**
 * Pruebas HTTP de seguridad contra una instancia local.
 *
 * Vacía la base de datos indicada, así que solo se ejecuta contra una base de
 * pruebas. Requiere que el servidor PHP esté levantado:
 *
 *   php -S 127.0.0.1:8099 -t .
 *
 * Uso (XAMPP, root sin contraseña):
 *   set DB_NAME=familycoins_test
 *   set TEST_BASE_URL=http://127.0.0.1:8099
 *   php tests/security_smoke.php
 */

require_once __DIR__ . '/../config/database.php';

$dbName = getenv('DB_NAME') === false ? 'familycoins_test' : getenv('DB_NAME');

if (stripos($dbName, 'test') === false) {
    fwrite(STDERR, "ABORTADO: DB_NAME debe apuntar a una base de datos de pruebas.\n");
    fwrite(STDERR, "Valor actual: {$dbName}\n");
    exit(2);
}

$base = rtrim(getenv('TEST_BASE_URL') === false ? 'http://127.0.0.1:8099' : getenv('TEST_BASE_URL'), '/');
$db = (new Database())->connect();
$cookies = [];
$pruebas = 0;
$fallos = 0;

function comprobar(string $descripcion, bool $condicion, string $detalle = ''): void
{
    global $pruebas, $fallos;

    $pruebas++;

    if ($condicion) {
        echo "  OK   {$descripcion}\n";

        return;
    }

    $fallos++;
    echo "  FALLO {$descripcion}" . ($detalle === '' ? '' : " -> {$detalle}") . "\n";
}

/**
 * @param array<string, mixed>|string|null $cuerpo
 * @param array<int, string> $cabeceras
 * @return array{status: int, cuerpo: string, json: mixed, cabeceras: array<int, string>, error: string}
 */
function http(
    string $metodo,
    string $ruta,
    array|string|null $cuerpo = null,
    ?string $sesion = null,
    array $cabeceras = []
): array {
    global $base, $cookies;

    $ch = curl_init($base . $ruta);
    $lineas = [];

    $opciones = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $cabeceras),
        CURLOPT_HEADERFUNCTION => static function ($ch, string $linea) use (&$lineas): int {
            $lineas[] = trim($linea);

            return strlen($linea);
        },
    ];

    if ($metodo === 'GET') {
        $opciones[CURLOPT_HTTPGET] = true;
    } else {
        $opciones[CURLOPT_CUSTOMREQUEST] = $metodo;
    }

    if ($cuerpo !== null) {
        $opciones[CURLOPT_POSTFIELDS] = is_array($cuerpo)
            ? json_encode($cuerpo, JSON_UNESCAPED_UNICODE)
            : $cuerpo;
    }

    if ($sesion !== null) {
        if (!isset($cookies[$sesion])) {
            $cookies[$sesion] = (string) tempnam(sys_get_temp_dir(), 'fc_cookie_');
        }

        $opciones[CURLOPT_COOKIEJAR] = $cookies[$sesion];
        $opciones[CURLOPT_COOKIEFILE] = $cookies[$sesion];
    }

    curl_setopt_array($ch, $opciones);

    $respuesta = curl_exec($ch);
    $estado = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return [
        'status' => $estado,
        'cuerpo' => is_string($respuesta) ? $respuesta : '',
        'json' => is_string($respuesta) ? json_decode($respuesta, true) : null,
        'cabeceras' => $lineas,
        'error' => $error,
    ];
}

function cookieDeSesion(?string $sesion): ?string
{
    global $cookies;

    if ($sesion === null || !isset($cookies[$sesion]) || !is_file($cookies[$sesion])) {
        return null;
    }

    foreach (file($cookies[$sesion], FILE_IGNORE_NEW_LINES) as $linea) {
        if (str_starts_with($linea, '#HttpOnly_')) {
            $linea = substr($linea, strlen('#HttpOnly_'));
        } elseif ($linea === '' || str_starts_with($linea, '#')) {
            continue;
        }

        $partes = explode("\t", $linea);

        if (count($partes) >= 7 && $partes[5] === session_name()) {
            return $partes[6];
        }
    }

    return null;
}

function datos(array $respuesta, string $ruta = 'data'): mixed
{
    if (!is_array($respuesta['json'])) {
        return null;
    }

    $valor = $respuesta['json'];

    foreach (explode('.', $ruta) as $clave) {
        if (!is_array($valor) || !array_key_exists($clave, $valor)) {
            return null;
        }

        $valor = $valor[$clave];
    }

    return $valor;
}

function esperarEstado(string $descripcion, int $esperado, array $respuesta): void
{
    comprobar(
        $descripcion,
        $respuesta['status'] === $esperado,
        'HTTP ' . $respuesta['status'] . ' ' . substr($respuesta['cuerpo'], 0, 200)
    );
}

function comprobarIgual(string $descripcion, mixed $esperado, mixed $obtenido): void
{
    comprobar(
        $descripcion . ' (esperado: ' . var_export($esperado, true)
        . ', obtenido: ' . var_export($obtenido, true) . ')',
        $esperado === $obtenido
    );
}

function limpiarBase(PDO $db): void
{
    // DELETE conserva el catálogo global de tienda_items (pareja_id NULL).
    $db->exec('DELETE FROM pareja_bonos');
    $db->exec('DELETE FROM pareja_tareas');
    $db->exec('DELETE FROM pareja_planta_cambios');
    $db->exec('DELETE FROM pareja_plantas');
    $db->exec('DELETE FROM pareja_streak_checkins');
    $db->exec('DELETE FROM pareja_streaks');
    $db->exec('DELETE FROM eventos');
    $db->exec('DELETE FROM recargas');
    $db->exec('DELETE FROM predefinidos');
    $db->exec('DELETE FROM parejas');
    $db->exec('DELETE FROM usuarios');
}

limpiarBase($db);

echo "Servidor: {$base}\n";
echo "Base de datos: {$dbName}\n\n";

try {
    echo "== Preparación ==\n";

    $registro1 = http('POST', '/api/register.php', [
        'nombre' => 'Daria',
        'email' => 'daria@example.com',
        'password' => 'Password123',
        'rol' => 'superadmin',
    ]);
    esperarEstado('registro de Daria', 201, $registro1);
    $id1 = (int) datos($registro1, 'data.id');
    $codigo1 = (string) datos($registro1, 'data.codigo');

    $registro2 = http('POST', '/api/register.php', [
        'nombre' => 'Alex',
        'email' => 'alex@example.com',
        'password' => 'Password123',
    ]);
    esperarEstado('registro de Alex', 201, $registro2);
    $id2 = (int) datos($registro2, 'data.id');
    $codigo2 = (string) datos($registro2, 'data.codigo');

    $registro3 = http('POST', '/api/register.php', [
        'nombre' => 'Intruso',
        'email' => 'intruso@example.com',
        'password' => 'Password123',
    ]);
    esperarEstado('registro de Intruso', 201, $registro3);
    $id3 = (int) datos($registro3, 'data.id');

    $registroAdmin = http('POST', '/api/register.php', [
        'nombre' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'Password123',
    ]);
    esperarEstado('registro del administrador', 201, $registroAdmin);
    $idAdmin = (int) datos($registroAdmin, 'data.id');

    $stmt = $db->prepare("UPDATE usuarios SET rol = 'superadmin' WHERE id = :id");
    $stmt->bindValue(':id', $idAdmin, PDO::PARAM_INT);
    $stmt->execute();

    comprobar('el registro ignora el rol enviado', (int) $db->query(
        "SELECT COUNT(*) FROM usuarios WHERE id = {$id1} AND rol = 'usuario'"
    )->fetchColumn() === 1);

    echo "\n== Cabeceras y salud ==\n";

    $health = http('GET', '/api/health.php');
    esperarEstado('health responde 200', 200, $health);
    comprobar('health confirma la base de datos', datos($health, 'data.base_de_datos') === 'ok');

    $cabecerasHealth = strtolower(implode(' | ', $health['cabeceras']));
    comprobar('se envía Cache-Control: no-store', str_contains($cabecerasHealth, 'cache-control: no-store'));
    comprobar('se envía X-Content-Type-Options', str_contains($cabecerasHealth, 'x-content-type-options: nosniff'));

    echo "\n== Autenticación y sesión ==\n";

    esperarEstado('saldos sin sesión devuelve 401', 401, http('GET', '/api/saldos.php'));
    esperarEstado('admin sin sesión devuelve 401', 401, http('GET', '/api/admin_usuarios_list.php'));

    esperarEstado('login con SQL injection falla', 401, http('POST', '/api/login.php', [
        'email' => "' OR '1'='1",
        'password' => "' OR '1'='1",
    ]));

    $loginMalFormado = http(
        'POST',
        '/api/login.php',
        '{"email":',
        null,
        ['Content-Type: application/json']
    );
    esperarEstado('JSON inválido devuelve 400', 400, $loginMalFormado);

    $login = http('POST', '/api/login.php', [
        'email' => 'daria@example.com',
        'password' => 'Password123',
    ], 'daria');
    esperarEstado('login correcto', 200, $login);
    comprobar('el login devuelve el usuario', (int) datos($login, 'data.id') === $id1);
    comprobar('el rol del registro no se escaló', datos($login, 'data.rol') === 'usuario');

    $cabecerasLogin = strtolower(implode(' | ', $login['cabeceras']));
    comprobar('la cookie es HttpOnly', str_contains($cabecerasLogin, 'httponly'));
    comprobar('la cookie es SameSite=Lax', str_contains($cabecerasLogin, 'samesite=lax'));
    comprobar('la sesión no se envía por HTTP sin Secure', !str_contains($cabecerasLogin, 'secure'));

    $jarFijacion = 'fijacion';
    http('GET', '/api/check_login.php', null, $jarFijacion);
    $cookieAntes = cookieDeSesion($jarFijacion);
    $loginFijacion = http('POST', '/api/login.php', [
        'email' => 'alex@example.com',
        'password' => 'Password123',
    ], $jarFijacion);
    esperarEstado('login con sesión previa', 200, $loginFijacion);
    $cookieDespues = cookieDeSesion($jarFijacion);
    comprobar(
        'el ID de sesión se regenera al iniciar sesión',
        $cookieAntes !== null && $cookieDespues !== null && $cookieAntes !== $cookieDespues
    );

    echo "\n== Control de acceso ==\n";

    esperarEstado('saldos sin pareja devuelve 403', 403, http('GET', '/api/saldos.php', null, 'daria'));
    esperarEstado('un usuario normal no entra en administración', 403, http(
        'GET',
        '/api/admin_usuarios_list.php',
        null,
        'daria'
    ));

    esperarEstado('vincular pareja', 200, http('POST', '/api/pareja_vincular.php', [
        'codigo' => $codigo2,
    ], 'daria'));

    $saldos = http('GET', '/api/saldos.php', null, 'daria');
    esperarEstado('saldos con pareja devuelve 200', 200, $saldos);
    comprobar('los saldos incluyen a los dos miembros', count((array) datos($saldos, 'data.usuarios')) === 2);

    echo "\n== Racha compartida ==\n";

    $zonaRacha = new DateTimeZone('Europe/Madrid');
    $hoyRacha = (new DateTimeImmutable('now', $zonaRacha))->format('Y-m-d');

    esperarEstado('la racha exige sesión', 401, http('GET', '/api/streak.php'));
    esperarEstado('el check-in exige sesión', 401, http('POST', '/api/streak_checkin.php'));
    esperarEstado('login de Intruso', 200, http('POST', '/api/login.php', [
        'email' => 'intruso@example.com',
        'password' => 'Password123',
    ], 'intruso'));
    esperarEstado('la racha exige pareja', 403, http('GET', '/api/streak.php', null, 'intruso'));

    $rachaInicial = http('GET', '/api/streak.php', null, 'daria');
    esperarEstado('la racha de la pareja responde 200', 200, $rachaInicial);
    comprobar('la racha empieza en cero', (int) datos($rachaInicial, 'data.racha_actual') === 0);
    comprobar('el día no está completo al empezar', datos($rachaInicial, 'data.completado_hoy') === false);

    $entrada1 = http('POST', '/api/streak_checkin.php', null, 'daria');
    esperarEstado('el check-in de Daria responde 200', 200, $entrada1);
    comprobar('el check-in de Daria se registra', datos($entrada1, 'data.registrada') === true);
    comprobar('una entrada sola no completa el día', datos($entrada1, 'data.completado_hoy') === false);
    comprobar('la racha sigue en cero con una entrada', (int) datos($entrada1, 'data.racha_actual') === 0);

    $repetida = http('POST', '/api/streak_checkin.php', null, 'daria');
    esperarEstado('el check-in repetido responde 200', 200, $repetida);
    comprobar('el check-in repetido no duplica', datos($repetida, 'data.registrada') === false);
    comprobar('el check-in repetido no avanza la racha', datos($repetida, 'data.completado_ahora') === false);

    esperarEstado('login de Alex', 200, http('POST', '/api/login.php', [
        'email' => 'alex@example.com',
        'password' => 'Password123',
    ], 'alex'));

    $entrada2 = http('POST', '/api/streak_checkin.php', null, 'alex');
    esperarEstado('el check-in de Alex responde 200', 200, $entrada2);
    comprobar('la segunda entrada completa el día', datos($entrada2, 'data.completado_hoy') === true);
    comprobar('la racha avanza a uno', (int) datos($entrada2, 'data.racha_actual') === 1);
    comprobar('la racha se marca como completada ahora', datos($entrada2, 'data.completado_ahora') === true);

    $repeticionPareja = http('POST', '/api/streak_checkin.php', null, 'alex');
    esperarEstado('repetir en la pareja responde 200', 200, $repeticionPareja);
    comprobar('repetir no vuelve a sumar', (int) datos($repeticionPareja, 'data.racha_actual') === 1);

    $checkLogin = http('GET', '/api/check_login.php', null, 'daria');
    esperarEstado('check_login devuelve la racha', 200, $checkLogin);
    comprobar('check_login incluye la racha', (int) datos($checkLogin, 'data.streak.racha_actual') === 1);

    $registros = (int) $db->query(
        "SELECT COUNT(*) FROM pareja_streak_checkins WHERE fecha = '{$hoyRacha}'"
    )->fetchColumn();
    comprobar('solo hay dos check-ins hoy, uno por persona', $registros === 2);

    esperarEstado('streak_checkin solo acepta POST', 405, http('GET', '/api/streak_checkin.php', null, 'daria'));

    esperarEstado('no se puede consultar el saldo de un tercero', 403, http(
        'GET',
        '/api/saldos.php?usuario_id=' . $id3,
        null,
        'daria'
    ));

    esperarEstado('no se puede crear un evento para un tercero', 403, http('POST', '/api/evento_create.php', [
        'usuario_id' => $id3,
        'nombre' => 'Ajeno',
        'fecha' => '2026-11-01',
        'coste' => 10,
        'efecto' => 'resta',
    ], 'daria'));

    esperarEstado('evento propio', 201, http('POST', '/api/evento_create.php', [
        'usuario_id' => $id1,
        'nombre' => 'Cena',
        'fecha' => '2026-11-01',
        'coste' => 20,
        'efecto' => 'resta',
    ], 'daria'));

    echo "\n== Validación de estadísticas ==\n";

    esperarEstado('stats válidas', 200, http('GET', '/api/stats.php?granularidad=mes', null, 'daria'));
    esperarEstado('granularidad inválida devuelve 400', 400, http(
        'GET',
        '/api/stats.php?granularidad=diaria',
        null,
        'daria'
    ));
    esperarEstado('fecha inválida devuelve 400', 400, http(
        'GET',
        '/api/stats.php?desde=2026-13-01',
        null,
        'daria'
    ));

    echo "\n== Administración ==\n";

    $loginAdmin = http('POST', '/api/login.php', [
        'email' => 'admin@example.com',
        'password' => 'Password123',
    ], 'admin');
    esperarEstado('login del superadministrador', 200, $loginAdmin);

    $usuarios = http('GET', '/api/admin_usuarios_list.php', null, 'admin');
    esperarEstado('listado de usuarios', 200, $usuarios);
    comprobar('el listado cuenta los usuarios', (int) datos($usuarios, 'total') >= 4);

    $inyeccion = http(
        'GET',
        '/api/admin_usuarios_list.php?q=' . urlencode("' OR 1=1 --"),
        null,
        'admin'
    );
    esperarEstado('la búsqueda con inyección no rompe la consulta', 200, $inyeccion);
    comprobar('la inyección no devuelve todos los usuarios', (int) datos($inyeccion, 'total') === 0);

    esperarEstado('contraseña débil rechazada', 400, http('POST', '/api/admin_usuario_create.php', [
        'nombre' => 'Débil',
        'email' => 'debil@example.com',
        'password' => 'abc',
    ], 'admin'));

    $creado = http('POST', '/api/admin_usuario_create.php', [
        'nombre' => 'Creado',
        'email' => 'creado@example.com',
        'password' => 'Password123',
        'rol' => 'usuario',
        'saldo_inicial' => 25,
        'activo' => true,
    ], 'admin');
    esperarEstado('crear usuario desde administración', 201, $creado);
    $idCreado = (int) datos($creado, 'data.id');
    comprobar('el saldo inicial se guarda', (int) datos($creado, 'data.saldo_inicial') === 25);

    esperarEstado('email duplicado devuelve 409', 409, http('POST', '/api/admin_usuario_create.php', [
        'nombre' => 'Duplicado',
        'email' => 'creado@example.com',
        'password' => 'Password123',
    ], 'admin'));

    esperarEstado('un admin no puede quitarse el rol', 409, http('POST', '/api/admin_usuario_update.php', [
        'id' => $idAdmin,
        'rol' => 'usuario',
    ], 'admin'));

    esperarEstado('un admin no puede darse de baja', 409, http('POST', '/api/admin_usuario_baja.php', [
        'id' => $idAdmin,
    ], 'admin'));

    esperarEstado('baja de otro usuario', 200, http('POST', '/api/admin_usuario_baja.php', [
        'id' => $idCreado,
    ], 'admin'));

    esperarEstado('el usuario dado de baja no puede iniciar sesión', 401, http('POST', '/api/login.php', [
        'email' => 'creado@example.com',
        'password' => 'Password123',
    ]));

    $resumen = http('GET', '/api/admin_resumen.php', null, 'admin');
    esperarEstado('resumen de administración', 200, $resumen);
    comprobar('el resumen calcula el saldo global', (int) datos($resumen, 'data.saldo_global') === 5);

    esperarEstado('eventos globales', 200, http('GET', '/api/admin_eventos_list.php?limite=10', null, 'admin'));
    esperarEstado('recargas globales', 200, http('GET', '/api/admin_recargas_list.php?limite=10', null, 'admin'));
    esperarEstado('estadísticas globales', 200, http(
        'GET',
        '/api/admin_stats.php?granularidad=trimestre',
        null,
        'admin'
    ));

    esperarEstado('crear o corregir pareja desde administración', 201, http('POST', '/api/admin_pareja_set.php', [
        'usuario_1_id' => $id1,
        'usuario_2_id' => $id3,
    ], 'admin'));

    esperarEstado('no se puede emparejar a un usuario consigo mismo', 400, http(
        'POST',
        '/api/admin_pareja_set.php',
        ['usuario_1_id' => $id1, 'usuario_2_id' => $id1],
        'admin'
    ));

    echo "\n== CSRF y métodos ==\n";

    esperarEstado('un Origin ajeno bloquea el POST', 403, http(
        'POST',
        '/api/login.php',
        ['email' => 'daria@example.com', 'password' => 'Password123'],
        null,
        ['Origin: http://evil.example']
    ));

    $csrfMismoOrigen = http(
        'POST',
        '/api/login.php',
        ['email' => 'daria@example.com', 'password' => 'incorrecta'],
        null,
        ['Origin: ' . $base]
    );
    esperarEstado('el mismo origen supera la comprobación', 401, $csrfMismoOrigen);

    $metodo = http('GET', '/api/evento_create.php');
    esperarEstado('método no permitido devuelve 405', 405, $metodo);
    comprobar(
        'la respuesta 405 incluye Allow',
        str_contains(strtolower(implode(' | ', $metodo['cabeceras'])), 'allow: post')
    );

    echo "\n== Cambio de contraseña ==\n";

    esperarEstado('cambio con contraseña actual incorrecta', 401, http('POST', '/api/password_change.php', [
        'password_actual' => 'Incorrecta123',
        'password_nueva' => 'Password789',
    ], 'daria'));

    esperarEstado('cambio de contraseña correcto', 200, http('POST', '/api/password_change.php', [
        'password_actual' => 'Password123',
        'password_nueva' => 'Password789',
    ], 'daria'));

    esperarEstado('la contraseña anterior ya no funciona', 401, http('POST', '/api/login.php', [
        'email' => 'daria@example.com',
        'password' => 'Password123',
    ]));

    esperarEstado('la contraseña nueva funciona', 200, http('POST', '/api/login.php', [
        'email' => 'daria@example.com',
        'password' => 'Password789',
    ]));

    echo "\n== Planta compartida ==\n";

    esperarEstado('el administrador asigna saldo para las pruebas de tienda', 200, http(
        'POST',
        '/api/admin_usuario_update.php',
        ['id' => $id1, 'saldo_inicial' => 300],
        'admin'
    ));

    $plantaInicial = http('GET', '/api/planta.php', null, 'daria');
    esperarEstado('la planta responde 200', 200, $plantaInicial);
    comprobar('la pareja empieza sin planta', datos($plantaInicial, 'data.planta') === null);
    comprobarIgual('el catálogo tiene seis plantas', 6, count((array) datos($plantaInicial, 'data.catalogo')));

    esperarEstado('elegir la primera planta es gratis', 200, http('POST', '/api/planta_elegir.php', [
        'planta_id' => 'monstera',
    ], 'daria'));
    $cambioPlantaHttp = http('POST', '/api/planta_elegir.php', [
        'planta_id' => 'cactus',
    ], 'daria');
    esperarEstado('cambiar de planta responde 200', 200, $cambioPlantaHttp);
    comprobar('el cambio de planta cuesta 20 coins', (int) datos($cambioPlantaHttp, 'coste') === 20);
    comprobar('la planta cambia de especie', datos($cambioPlantaHttp, 'data.planta.planta_id') === 'cactus');

    esperarEstado('una planta inexistente devuelve 400', 400, http('POST', '/api/planta_elegir.php', [
        'planta_id' => 'planta_inventada',
    ], 'daria'));

    echo "\n== Tienda y tareas ==\n";

    $tiendaHttp = http('GET', '/api/tienda.php', null, 'daria');
    esperarEstado('la tienda responde 200', 200, $tiendaHttp);
    comprobar('la tienda incluye artículos globales', count((array) datos($tiendaHttp, 'data.items')) >= 11);
    comprobarIgual('la pareja empieza sin artículos propios', 0, (int) datos($tiendaHttp, 'data.personalizados'));

    $items = (array) datos($tiendaHttp, 'data.items');
    $porClave = [];

    foreach ($items as $item) {
        if (isset($item['clave'])) {
            $porClave[$item['clave']] = $item;
        }
    }

    $creadoHttp = http('POST', '/api/tienda_item_create.php', [
        'tipo' => 'recompensa',
        'nombre' => 'Artículo de prueba',
        'descripcion' => 'Creado desde la prueba HTTP',
        'coste' => 10,
    ], 'daria');
    esperarEstado('crear un artículo propio responde 201', 201, $creadoHttp);
    $itemPropioId = (int) datos($creadoHttp, 'data.id');
    comprobar('el artículo propio pertenece a la pareja', datos($creadoHttp, 'data.alcance') === 'pareja');

    esperarEstado('un artículo de más de 100 coins se rechaza', 400, http('POST', '/api/tienda_item_create.php', [
        'tipo' => 'recompensa',
        'nombre' => 'Demasiado caro',
        'coste' => 101,
    ], 'daria'));

    esperarEstado('un tipo de artículo inválido se rechaza', 400, http('POST', '/api/tienda_item_create.php', [
        'tipo' => 'inventado',
        'nombre' => 'Tipo raro',
        'coste' => 10,
    ], 'daria'));

    esperarEstado('actualizar el artículo propio', 200, http('POST', '/api/tienda_item_update.php', [
        'id' => $itemPropioId,
        'tipo' => 'permiso',
        'nombre' => 'Artículo actualizado',
        'descripcion' => 'Descripción nueva',
        'coste' => 20,
    ], 'daria'));

    $tareasVacias = http('GET', '/api/tareas.php', null, 'daria');
    esperarEstado('listar tareas responde 200', 200, $tareasVacias);
    comprobarIgual('no hay tareas al empezar', 0, (int) datos($tareasVacias, 'meta.total'));

    $saldoActual = static function () use ($id1): int {
        $respuesta = http('GET', '/api/saldos.php', null, 'daria');

        foreach ((array) datos($respuesta, 'data.usuarios') as $fila) {
            if ((int) $fila['usuario_id'] === $id1) {
                return (int) $fila['saldo'];
            }
        }

        return -999999;
    };

    $saldoAntesCompra = $saldoActual();
    $compraHttp = http('POST', '/api/tarea_create.php', [
        'item_id' => (int) $porClave['pelicula']['id'],
    ], 'daria');
    esperarEstado('comprar una recompensa responde 201', 201, $compraHttp);
    $tareaCompraId = (int) datos($compraHttp, 'data.id');
    comprobar('la compra queda pendiente', datos($compraHttp, 'data.estado') === 'pendiente');
    comprobarIgual('la compra descuenta 15 coins', $saldoAntesCompra - 15, $saldoActual());

    esperarEstado('el solicitante no puede confirmar su compra', 403, http(
        'POST',
        '/api/tarea_confirmar.php',
        ['id' => $tareaCompraId],
        'daria'
    ));
    esperarEstado('la pareja confirma la compra', 200, http(
        'POST',
        '/api/tarea_confirmar.php',
        ['id' => $tareaCompraId],
        'intruso'
    ));
    comprobarIgual('confirmar no vuelve a cobrar', $saldoAntesCompra - 15, $saldoActual());

    $saldoAntesRechazo = $saldoActual();
    $compraRechazo = http('POST', '/api/tarea_create.php', [
        'item_id' => (int) $porClave['pelicula']['id'],
    ], 'daria');
    esperarEstado('crear la compra que se rechazará', 201, $compraRechazo);
    $tareaRechazoId = (int) datos($compraRechazo, 'data.id');
    esperarEstado('la pareja rechaza la compra', 200, http(
        'POST',
        '/api/tarea_rechazar.php',
        ['id' => $tareaRechazoId],
        'intruso'
    ));
    comprobarIgual('el rechazo devuelve las coins', $saldoAntesRechazo, $saldoActual());

    $saldoAntesCancelacion = $saldoActual();
    $compraCancelacion = http('POST', '/api/tarea_create.php', [
        'item_id' => (int) $porClave['pelicula']['id'],
    ], 'daria');
    esperarEstado('crear la compra que se cancelará', 201, $compraCancelacion);
    $tareaCancelacionId = (int) datos($compraCancelacion, 'data.id');
    esperarEstado('el solicitante cancela la compra', 200, http(
        'POST',
        '/api/tarea_cancelar.php',
        ['id' => $tareaCancelacionId],
        'daria'
    ));
    comprobarIgual('la cancelación devuelve las coins', $saldoAntesCancelacion, $saldoActual());

    $saldoAntesGanancia = $saldoActual();
    $gananciaHttp = http('POST', '/api/tarea_create.php', [
        'item_id' => (int) $porClave['ayuda_extra']['id'],
    ], 'daria');
    esperarEstado('crear una tarea de ganancia responde 201', 201, $gananciaHttp);
    $tareaGananciaId = (int) datos($gananciaHttp, 'data.id');
    comprobar('la ganancia no crea evento al pedirla', datos($gananciaHttp, 'data.evento_id') === null);
    comprobarIgual('la ganancia no descuenta coins al pedirla', $saldoAntesGanancia, $saldoActual());
    esperarEstado('la pareja confirma la ganancia', 200, http(
        'POST',
        '/api/tarea_confirmar.php',
        ['id' => $tareaGananciaId],
        'intruso'
    ));
    comprobarIgual('la ganancia suma 25 coins', $saldoAntesGanancia + 25, $saldoActual());

    $tareasPendientes = http('GET', '/api/tareas.php?estado=pendiente', null, 'daria');
    esperarEstado('filtrar tareas por estado', 200, $tareasPendientes);
    comprobarIgual('no quedan tareas pendientes', 0, (int) datos($tareasPendientes, 'meta.total'));

    $tareasTodas = http('GET', '/api/tareas.php', null, 'intruso');
    esperarEstado('la pareja ve el historial de tareas', 200, $tareasTodas);
    comprobar('el historial incluye las tareas resueltas', count((array) datos($tareasTodas, 'data')) >= 4);

    esperarEstado('desactivar el artículo propio', 200, http(
        'POST',
        '/api/tienda_item_delete.php',
        ['id' => $itemPropioId],
        'daria'
    ));

    echo "\n== Fechas especiales y bonos ==\n";

    esperarEstado('guardar el cumpleaños del perfil', 200, http(
        'POST',
        '/api/perfil_fecha_nacimiento.php',
        ['fecha_nacimiento' => $hoyRacha],
        'daria'
    ));
    $aniversarioAnterior = (new DateTimeImmutable('now', $zonaRacha))
        ->modify('-1 year')
        ->format('Y-m-d');
    esperarEstado('guardar el aniversario de la pareja', 200, http(
        'POST',
        '/api/pareja_aniversario.php',
        ['fecha_aniversario' => $aniversarioAnterior],
        'daria'
    ));

    $perfil = http('GET', '/api/perfil.php', null, 'daria');
    esperarEstado('consultar el perfil responde 200', 200, $perfil);
    comprobar('el perfil incluye el cumpleaños', datos($perfil, 'data.usuario.fecha_nacimiento') === $hoyRacha);
    comprobar('el perfil incluye el aniversario', datos($perfil, 'data.pareja_info.fecha_aniversario') === $aniversarioAnterior);

    $saldoDariaAntesBonos = $saldoActual();
    $saldoIntrusoAntesBonos = (static function () use ($id3): int {
        $respuesta = http('GET', '/api/saldos.php', null, 'intruso');

        foreach ((array) datos($respuesta, 'data.usuarios') as $fila) {
            if ((int) $fila['usuario_id'] === $id3) {
                return (int) $fila['saldo'];
            }
        }

        return -999999;
    })();

    $checkBonos = http('GET', '/api/check_login.php', null, 'daria');
    esperarEstado('check_login procesa los bonos', 200, $checkBonos);
    comprobarIgual('se entregan tres bonos', 3, count((array) datos($checkBonos, 'data.bonos')));
    comprobarIgual('el cumpleaños y el aniversario suman 100 a Daria', $saldoDariaAntesBonos + 100, $saldoActual());
    comprobarIgual(
        'el aniversario suma 50 a Intruso',
        $saldoIntrusoAntesBonos + 50,
        (static function () use ($id3): int {
            $respuesta = http('GET', '/api/saldos.php', null, 'intruso');

            foreach ((array) datos($respuesta, 'data.usuarios') as $fila) {
                if ((int) $fila['usuario_id'] === $id3) {
                    return (int) $fila['saldo'];
                }
            }

            return -999999;
        })()
    );

    $checkBonosRepetido = http('GET', '/api/check_login.php', null, 'daria');
    esperarEstado('repetir check_login responde 200', 200, $checkBonosRepetido);
    comprobarIgual('los bonos no se repiten', 0, count((array) datos($checkBonosRepetido, 'data.bonos')));
    comprobar('los eventos de bono se registran', (int) $db->query(
        "SELECT COUNT(*) FROM eventos WHERE origen = 'bono'"
    )->fetchColumn() === 3);

    echo "\n== Protección de eventos del sistema ==\n";

    $eventoBono = (int) $db->query(
        "SELECT id FROM eventos WHERE origen = 'bono' ORDER BY id LIMIT 1"
    )->fetchColumn();
    esperarEstado('un evento de bono no se puede anular desde el historial', 409, http(
        'POST',
        '/api/evento_anular.php',
        ['id' => $eventoBono],
        'daria'
    ));
    esperarEstado('un evento de bono no se puede editar desde el historial', 409, http(
        'POST',
        '/api/evento_update.php',
        ['id' => $eventoBono, 'nombre' => 'Intento'],
        'daria'
    ));

    echo "\n== Sesión de una cuenta dada de baja ==\n";

    esperarEstado('Intruso inicia sesión', 200, http('POST', '/api/login.php', [
        'email' => 'intruso@example.com',
        'password' => 'Password123',
    ], 'intruso'));

    esperarEstado('baja de Intruso', 200, http('POST', '/api/admin_usuario_baja.php', [
        'id' => $id3,
    ], 'admin'));

    esperarEstado('la sesión abierta deja de servir', 401, http('GET', '/api/saldos.php', null, 'intruso'));
} catch (Throwable $e) {
    $fallos++;
    echo "\nEXCEPCIÓN: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
} finally {
    foreach ($cookies as $archivo) {
        if (is_file($archivo)) {
            @unlink($archivo);
        }
    }

    limpiarBase($db);
}

echo "\n" . str_repeat('-', 50) . "\n";
echo "Pruebas: {$pruebas}  Fallos: {$fallos}\n";

exit($fallos === 0 ? 0 : 1);
