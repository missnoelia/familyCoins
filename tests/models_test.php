<?php
declare(strict_types=1);

// Mantiene la salida en buffer para que session_regenerate_id() no falle
// cuando el test ya ha escrito por pantalla.
ob_start();

/**
 * Pruebas de modelos contra PostgreSQL.
 *
 * Se ejecutan sobre una base de datos de pruebas porque vacían las tablas.
 *
 * Uso:
 *   set DB_NAME=familycoins_test
 *   set DB_USER=familycoins_app
 *   set DB_PASS=FamilyCoinsLocal2026
 *   php -d extension=pdo_pgsql tests/models_test.php
 */

require_once __DIR__ . '/../config/bootstrap.php';

$dbName = getenv('DB_NAME') === false ? 'couplecoins_db' : getenv('DB_NAME');

if (stripos($dbName, 'test') === false) {
    fwrite(STDERR, "ABORTADO: DB_NAME debe apuntar a una base de datos de pruebas.\n");
    fwrite(STDERR, "Valor actual: {$dbName}\n");
    exit(2);
}

$db = (new Database())->connect();
$fallos = 0;
$pruebas = 0;

function comprobar(string $descripcion, bool $condicion): void
{
    global $fallos, $pruebas;

    $pruebas++;

    if ($condicion) {
        echo "  OK   {$descripcion}\n";

        return;
    }

    $fallos++;
    echo "  FALLO {$descripcion}\n";
}

function comprobarIgual(string $descripcion, mixed $esperado, mixed $obtenido): void
{
    comprobar(
        $descripcion . ' (esperado: ' . var_export($esperado, true)
        . ', obtenido: ' . var_export($obtenido, true) . ')',
        $esperado === $obtenido
    );
}

function limpiar(PDO $db): void
{
    // Se usa DELETE en lugar de TRUNCATE ... CASCADE para no borrar el
    // catálogo global de tienda_items, que no pertenece a ninguna pareja.
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

echo "Base de datos: {$dbName}\n\n";

limpiar($db);

$userModel = new User($db);
$coupleModel = new Couple($db);
$predefinedModel = new Predefined($db);
$eventModel = new Event($db);
$rechargeModel = new Recharge($db);
$balanceModel = new Balance($db);
$statsModel = new Stats($db);
$streakModel = new Streak($db);
$plantModel = new Plant($db);
$shopModel = new Shop($db);
$bonusModel = new Bonus($db);

try {
    echo "== Usuarios ==\n";

    $u1 = $userModel->register('Daria', 'daria@example.com', 'Password123', 'usuario', 100);
    $u2 = $userModel->register('Alex', 'alex@example.com', 'Password123', 'usuario', 0);
    $u3 = $userModel->register('Admin', 'admin@example.com', 'Password123', 'superadmin');

    comprobar('register devuelve un ID', is_int($u1) && $u1 > 0);
    comprobar('register rechaza emails duplicados', $userModel->register(
        'Otra',
        'DARIA@example.com',
        'Password123'
    ) === false);

    $guardado = $userModel->getById((int) $u1);
    comprobar('el código tiene formato AAA0000', preg_match('/^[A-Z]{3}[0-9]{4}$/', (string) $guardado['codigo']) === 1);
    comprobar('el saldo inicial se guarda', (int) $guardado['saldo_inicial'] === 100);
    comprobar('la cuenta nace activa', User::esActivo($guardado['activo']));

    $login = $userModel->login('daria@example.com', 'Password123');
    comprobar('login correcto', is_array($login) && (int) $login['id'] === $u1);
    comprobar('login con contraseña incorrecta falla', $userModel->login('daria@example.com', 'Wrong123') === false);
    comprobar('login con email inexistente falla', $userModel->login('nadie@example.com', 'Password123') === false);
    comprobar('login SQL injection falla', $userModel->login("' OR '1'='1", "' OR '1'='1") === false);

    echo "\n== Parejas ==\n";

    $parejaId = $coupleModel->link((int) $u1, (int) $u2);
    comprobar('link crea la pareja', $parejaId > 0);
    comprobar('hasPartner detecta la pareja', $coupleModel->hasPartner((int) $u1));
    comprobarIgual('getPartner devuelve al otro miembro', (int) $u2, (int) $coupleModel->getPartner((int) $u1)['id']);
    comprobar('no se puede vincular dos veces', (static function () use ($coupleModel, $u1): bool {
        try {
            $coupleModel->link((int) $u1, 999999);

            return false;
        } catch (ApiException $e) {
            return true;
        }
    })());

    echo "\n== Racha de pareja ==\n";

    $zonaRacha = new DateTimeZone('Europe/Madrid');
    $hoyRacha = (new DateTimeImmutable('now', $zonaRacha))->format('Y-m-d');
    $ayerRacha = (new DateTimeImmutable('now', $zonaRacha))->modify('-1 day')->format('Y-m-d');

    $estadoInicial = $streakModel->getEstado((int) $u1);
    comprobarIgual('la racha empieza en cero', 0, $estadoInicial['racha_actual']);
    comprobar('el día aún no está completo', !$estadoInicial['completado_hoy']);

    $entradaU1 = $streakModel->registrarEntrada((int) $u1);
    comprobar('la primera entrada se registra', $entradaU1['registrada']);
    comprobar('una sola entrada no completa el día', !$entradaU1['completado_hoy']);
    comprobarIgual('la racha sigue en cero', 0, $entradaU1['racha_actual']);

    $repetidaU1 = $streakModel->registrarEntrada((int) $u1);
    comprobar('la entrada repetida no duplica', !$repetidaU1['registrada']);
    comprobarIgual('solo hay un check-in de Daria hoy', 1, (int) $db->query(
        "SELECT COUNT(*) FROM pareja_streak_checkins WHERE usuario_id = {$u1} AND fecha = DATE '{$hoyRacha}'"
    )->fetchColumn());

    $entradaU2 = $streakModel->registrarEntrada((int) $u2);
    comprobar('la entrada de la pareja completa el día', $entradaU2['completado_hoy']);
    comprobar('el día se completa al entrar ambos', $entradaU2['completado_ahora']);
    comprobarIgual('la racha avanza a uno', 1, $entradaU2['racha_actual']);
    comprobarIgual('la racha máxima es uno', 1, $entradaU2['racha_maxima']);

    $repetidaU2 = $streakModel->registrarEntrada((int) $u2);
    comprobar('repetir no avanza la racha', !$repetidaU2['completado_ahora']);
    comprobarIgual('la racha no se duplica', 1, $repetidaU2['racha_actual']);

    // Simula que la racha venía de ayer.
    $db->exec("DELETE FROM pareja_streak_checkins WHERE fecha = DATE '{$hoyRacha}'");
    $db->exec(
        "UPDATE pareja_streaks SET racha_actual = 5, racha_maxima = 5, "
        . "ultimo_dia_completo = DATE '{$ayerRacha}' WHERE pareja_id = {$parejaId}"
    );

    $streakModel->registrarEntrada((int) $u1);
    $continuada = $streakModel->registrarEntrada((int) $u2);
    comprobarIgual('la racha continúa desde ayer', 6, $continuada['racha_actual']);
    comprobarIgual('la racha máxima se actualiza', 6, $continuada['racha_maxima']);

    // Simula que se saltó un día.
    $huecoRacha = (new DateTimeImmutable('now', $zonaRacha))->modify('-3 days')->format('Y-m-d');
    $db->exec("DELETE FROM pareja_streak_checkins WHERE fecha = DATE '{$hoyRacha}'");
    $db->exec(
        "UPDATE pareja_streaks SET racha_actual = 8, racha_maxima = 8, "
        . "ultimo_dia_completo = DATE '{$huecoRacha}' WHERE pareja_id = {$parejaId}"
    );

    $rachaVieja = $streakModel->getEstado((int) $u1);
    comprobarIgual('una racha vieja se muestra como cero', 0, $rachaVieja['racha_actual']);

    $streakModel->registrarEntrada((int) $u1);
    $reiniciada = $streakModel->registrarEntrada((int) $u2);
    comprobarIgual('tras un hueco la racha vuelve a uno', 1, $reiniciada['racha_actual']);
    comprobarIgual('la racha máxima se conserva', 8, $reiniciada['racha_maxima']);

    comprobar('sin pareja no hay racha', (static function () use ($streakModel, $u3): bool {
        try {
            $streakModel->getEstado((int) $u3);

            return false;
        } catch (ApiException $e) {
            return $e->httpStatus() === 403;
        }
    })());

    echo "\n== Predefinidos ==\n";

    $predefinidoId = $predefinedModel->create((int) $u1, 'Cena', 20, 'resta');
    comprobar('create devuelve el ID', $predefinidoId > 0);
    comprobar('nombre duplicado detectado', $predefinedModel->nombreExiste((int) $u1, 'cena'));
    comprobar(
        'otro usuario no puede leer el predefinido',
        $predefinedModel->getById($predefinidoId, (int) $u2) === null
    );

    echo "\n== Eventos y recargas ==\n";

    $eventoResta = $eventModel->create((int) $u1, (int) $u1, $predefinidoId, 'Cena', '2026-11-02', 20, 'resta');
    $eventoSuma = $eventModel->create((int) $u2, (int) $u1, null, 'Cine', '2026-11-03', 10, 'suma');
    $eventoSuma2 = $eventModel->create((int) $u1, (int) $u1, null, 'Regalo', '2026-11-04', 5, 'suma');
    $eventoAnulado = $eventModel->create((int) $u1, (int) $u1, null, 'Anulado', '2026-11-05', 7, 'resta');
    $eventModel->anular($eventoAnulado);
    $rechargeModel->create((int) $u1, 50, '2026-11-01', 'Recompensa');

    comprobar('pertenecePareja acepta al titular', $eventModel->pertenecePareja((int) $u1, (int) $u1));
    comprobar('pertenecePareja acepta a la pareja', $eventModel->pertenecePareja((int) $u1, (int) $u2));
    comprobar('pertenecePareja rechaza a terceros', !$eventModel->pertenecePareja((int) $u1, (int) $u3));

    echo "\n== Saldos ==\n";

    $saldos = $balanceModel->getSaldos((int) $u1);
    $saldoU1 = $balanceModel->getSaldoUsuario((int) $u1);
    $saldoU2 = $balanceModel->getSaldoUsuario((int) $u2);

    comprobarIgual('saldo de Daria', 140, $saldoU1);
    comprobarIgual('saldo de Alex', 5, $saldoU2);
    comprobarIgual('saldo conjunto', 145, $saldos['saldo_conjunto']);

    $filaU1 = array_values(array_filter(
        $saldos['usuarios'],
        static fn (array $fila): bool => (int) $fila['usuario_id'] === $u1
    ))[0];

    comprobarIgual('total de recargas de Daria', 50, $filaU1['total_recargas']);
    comprobarIgual('total restado a Daria', 20, $filaU1['total_resta']);
    comprobarIgual('total sumado a Daria', 10, $filaU1['total_suma']);

    echo "\n== Estadísticas ==\n";

    $stats = $statsModel->getEstadisticas((int) $u1, 'mes', '2026-01-01', '2026-12-31');
    comprobarIgual('se generan 12 meses', 12, count($stats['data']));

    $noviembre = null;

    foreach ($stats['data'] as $periodo) {
        if ($periodo['periodo'] === '2026-11') {
            $noviembre = $periodo;
        }
    }

    comprobar('existe el periodo 2026-11', $noviembre !== null);
    comprobarIgual('eventos de noviembre', 3, $noviembre['eventos']);
    comprobarIgual('resta de noviembre', 20, $noviembre['total_resta']);
    comprobarIgual('suma de noviembre', 15, $noviembre['total_suma']);
    comprobarIgual('recargas de noviembre', 50, $noviembre['recargas']);
    comprobarIgual('neto de noviembre', -5, $noviembre['neto']);
    comprobarIgual('variación de noviembre', 45, $noviembre['variacion']);
    comprobarIgual('total de eventos', 3, $stats['meta']['total_eventos']);

    $statsU2 = $statsModel->getEstadisticas((int) $u1, 'mes', '2026-01-01', '2026-12-31', (int) $u2);
    $noviembreU2 = array_values(array_filter(
        $statsU2['data'],
        static fn (array $periodo): bool => $periodo['periodo'] === '2026-11'
    ))[0];
    comprobarIgual('eventos de Alex en noviembre', 1, $noviembreU2['eventos']);
    comprobarIgual('suma de Alex en noviembre', 10, $noviembreU2['total_suma']);
    comprobarIgual('recargas de Alex en noviembre', 0, $noviembreU2['recargas']);

    $global = $statsModel->getEstadisticasGlobales('trimestre', '2026-01-01', '2026-12-31');
    comprobarIgual('se generan 4 trimestres', 4, count($global['data']));
    comprobarIgual('total global de eventos', 3, $global['meta']['total_eventos']);

    echo "\n== Administración ==\n";

    $listado = $userModel->adminList(['limite' => 10, 'offset' => 0]);
    comprobarIgual('adminList cuenta los usuarios', 3, $listado['total']);

    $soloAdmin = $userModel->adminList(['limite' => 10, 'offset' => 0, 'rol' => 'superadmin']);
    comprobarIgual('filtro por rol', 1, $soloAdmin['total']);

    $conPareja = $userModel->adminList(['limite' => 10, 'offset' => 0, 'con_pareja' => true]);
    comprobarIgual('filtro por pareja', 2, $conPareja['total']);

    $busqueda = $userModel->adminList(['limite' => 10, 'offset' => 0, 'q' => 'ALEX']);
    comprobarIgual('búsqueda por nombre', 1, $busqueda['total']);

    comprobarIgual('hay un superadmin activo', 1, $userModel->contarSuperadminsActivos());

    $userModel->adminUpdate((int) $u2, 'Alex Actualizado', 'alex2@example.com', 'usuario', 10, true);
    $actualizado = $userModel->getById((int) $u2);
    comprobarIgual('adminUpdate cambia el nombre', 'Alex Actualizado', $actualizado['nombre']);
    comprobarIgual('adminUpdate cambia el saldo', 10, $actualizado['saldo_inicial']);

    comprobar('adminUpdate rechaza email duplicado', (static function () use ($userModel, $u2): bool {
        try {
            $userModel->adminUpdate((int) $u2, 'Alex', 'admin@example.com', 'usuario', null, true);

            return false;
        } catch (ApiException $e) {
            return $e->httpStatus() === 409;
        }
    })());

    $adminListado = $eventModel->adminList(['limite' => 10, 'offset' => 0]);
    comprobarIgual('adminList de eventos', 4, $adminListado['total']);

    $adminFiltrado = $eventModel->adminList([
        'limite' => 10,
        'offset' => 0,
        'usuario_id' => (int) $u1,
        'anulado' => false,
    ]);
    comprobarIgual('adminList filtra por usuario y anulado', 2, $adminFiltrado['total']);

    $adminRecargas = $rechargeModel->adminList(['limite' => 10, 'offset' => 0, 'q' => 'recompensa']);
    comprobarIgual('adminList de recargas', 1, $adminRecargas['total']);

    $u4 = $userModel->register('Nuevo', 'nuevo@example.com', 'Password123');
    $resultadoPareja = $coupleModel->adminSet((int) $u1, (int) $u4);
    comprobar('adminSet crea la pareja', $resultadoPareja['creada']);
    comprobarIgual('la pareja antigua se elimina', 1, $coupleModel->adminList([
        'limite' => 10,
        'offset' => 0,
    ])['total']);

    $parejaAdmin = $coupleModel->adminGet($resultadoPareja['id']);
    comprobar('adminGet devuelve la pareja', $parejaAdmin !== null);
    comprobar('adminDelete elimina la pareja', $coupleModel->adminDelete($resultadoPareja['id']));
    comprobarIgual('ya no quedan parejas', 0, (int) $db->query('SELECT COUNT(*) FROM parejas')->fetchColumn());

    $userModel->adminDeactivate((int) $u4);
    comprobar('la baja impide el login', $userModel->login('nuevo@example.com', 'Password123') === false);

    $userModel->changePassword((int) $u1, 'Password123', 'Password456');
    comprobar('changePassword actualiza el hash', $userModel->login('daria@example.com', 'Password456') !== false);
    comprobar('la contraseña anterior ya no vale', $userModel->login('daria@example.com', 'Password123') === false);

    echo "\n== Planta compartida ==\n";

    $u5 = $userModel->register('Planta', 'planta@example.com', 'Password123', 'usuario', 200);
    $u6 = $userModel->register('Pareja', 'pareja@example.com', 'Password123', 'usuario', 0);
    comprobar('registro de los usuarios de planta', is_int($u5) && is_int($u6) && $u5 > 0 && $u6 > 0);

    $parejaNueva = $coupleModel->link((int) $u5, (int) $u6);
    comprobar('link guarda el aniversario por defecto', $coupleModel->getParejaDeUsuario((int) $u5)['fecha_aniversario'] === api_hoy()->format('Y-m-d'));

    $estadoSinPlanta = $plantModel->getEstado((int) $u5);
    comprobar('la pareja empieza sin planta', $estadoSinPlanta['planta'] === null);
    comprobarIgual('hay seis plantas disponibles', 6, count($estadoSinPlanta['catalogo']));

    $primeraPlanta = $plantModel->elegir((int) $u5, 'monstera');
    comprobarIgual('la primera planta es gratis', 0, $primeraPlanta['coste']);
    comprobarIgual('la planta empieza en semilla', 0, $primeraPlanta['estado']['planta']['etapa']);

    $cambioPlanta = $plantModel->elegir((int) $u5, 'cactus');
    comprobarIgual('cambiar de planta cuesta 20 coins', 20, $cambioPlanta['coste']);
    comprobarIgual('el cambio conserva la etapa', 0, $cambioPlanta['estado']['planta']['etapa']);
    comprobar('el cambio deja un evento de origen planta', (int) $db->query(
        "SELECT COUNT(*) FROM eventos WHERE origen = 'planta' AND usuario_id = {$u5}"
    )->fetchColumn() === 1);

    $plantModel->registrarDiaCompletado($parejaNueva);
    $plantModel->registrarDiaCompletado($parejaNueva);
    $plantModel->registrarDiaCompletado($parejaNueva);
    $estadoCrecida = $plantModel->getEstado((int) $u5);
    comprobarIgual('tres días hacen crecer a brote', 1, $estadoCrecida['planta']['etapa']);
    comprobarIgual('el progreso vuelve a cero tras subir de etapa', 0, $estadoCrecida['planta']['progreso_dias']);
    comprobarIgual('la etapa máxima se conserva', 1, $estadoCrecida['planta']['etapa_maxima']);

    $haceDosDias = api_hoy()->modify('-2 days')->format('Y-m-d');
    $db->exec(
        "INSERT INTO pareja_streaks (pareja_id, racha_actual, racha_maxima, ultimo_dia_completo)
         VALUES ({$parejaNueva}, 3, 3, DATE '{$haceDosDias}')
         ON CONFLICT (pareja_id) DO UPDATE
         SET racha_actual = 3, racha_maxima = 3, ultimo_dia_completo = DATE '{$haceDosDias}'"
    );
    $db->exec(
        "UPDATE pareja_plantas
         SET etapa = 2, etapa_maxima = 2, progreso_dias = 2,
             degradada_para = NULL, reiniciada_para = NULL
         WHERE pareja_id = {$parejaNueva}"
    );
    $plantModel->sincronizar($parejaNueva);
    $trasRotura = $db->query(
        "SELECT etapa, progreso_dias FROM pareja_plantas WHERE pareja_id = {$parejaNueva}"
    )->fetch(PDO::FETCH_ASSOC);
    comprobarIgual('una racha rota baja una etapa', 1, (int) $trasRotura['etapa']);
    comprobarIgual('una racha rota reinicia el progreso', 0, (int) $trasRotura['progreso_dias']);

    $plantModel->sincronizar($parejaNueva);
    comprobarIgual('la rotura solo se aplica una vez', 1, (int) $db->query(
        "SELECT etapa FROM pareja_plantas WHERE pareja_id = {$parejaNueva}"
    )->fetchColumn());

    $haceCuatroDias = api_hoy()->modify('-4 days')->format('Y-m-d');
    $db->exec(
        "UPDATE pareja_streaks SET ultimo_dia_completo = DATE '{$haceCuatroDias}'
         WHERE pareja_id = {$parejaNueva}"
    );
    $db->exec(
        "UPDATE pareja_plantas
         SET etapa = 3, etapa_maxima = 3, progreso_dias = 5,
             degradada_para = DATE '{$haceCuatroDias}', reiniciada_para = NULL
         WHERE pareja_id = {$parejaNueva}"
    );
    $plantModel->sincronizar($parejaNueva);
    $trasReinicio = $db->query(
        "SELECT etapa, etapa_maxima, progreso_dias FROM pareja_plantas WHERE pareja_id = {$parejaNueva}"
    )->fetch(PDO::FETCH_ASSOC);
    comprobarIgual('cuatro días sin racha reinician la planta', 0, (int) $trasReinicio['etapa']);
    comprobarIgual('el reinicio conserva la etapa máxima', 3, (int) $trasReinicio['etapa_maxima']);
    comprobarIgual('el reinicio borra el progreso', 0, (int) $trasReinicio['progreso_dias']);

    // Integración con la racha: completar el día debe sumar un día de planta.
    $ayerPlanta = api_hoy()->modify('-1 day')->format('Y-m-d');
    $db->exec("DELETE FROM pareja_streak_checkins WHERE pareja_id = {$parejaNueva}");
    $db->exec(
        "UPDATE pareja_streaks
         SET racha_actual = 1, racha_maxima = 3, ultimo_dia_completo = DATE '{$ayerPlanta}'
         WHERE pareja_id = {$parejaNueva}"
    );
    $streakModel->registrarEntrada((int) $u5);
    $streakModel->registrarEntrada((int) $u6);
    comprobarIgual('completar la racha hace crecer la planta', 1, (int) $db->query(
        "SELECT progreso_dias FROM pareja_plantas WHERE pareja_id = {$parejaNueva}"
    )->fetchColumn());

    echo "\n== Tienda y tareas ==\n";

    $tienda = $shopModel->getTienda((int) $u5);
    $itemsPorClave = [];

    foreach ($tienda['items'] as $item) {
        if ($item['clave'] !== null) {
            $itemsPorClave[$item['clave']] = $item;
        }
    }

    comprobar('la tienda incluye el catálogo global', isset($itemsPorClave['pelicula'], $itemsPorClave['ayuda_extra']));
    comprobarIgual('la pareja empieza sin artículos propios', 0, $tienda['personalizados']);
    comprobarIgual('el máximo de artículos propios es 15', 15, $tienda['maximo_personalizados']);
    comprobarIgual('el coste máximo es 100', 100, $tienda['coste_maximo']);

    $primerCustom = $shopModel->crearItem((int) $u5, 'recompensa', 'Custom 1', 'Prueba', 5);

    for ($i = 2; $i <= 15; $i++) {
        $shopModel->crearItem((int) $u5, 'recompensa', 'Custom ' . $i, null, 5);
    }

    comprobarIgual('se pueden crear quince artículos propios', 15, $shopModel->getTienda((int) $u5)['personalizados']);
    comprobar('el artículo dieciséis se rechaza', (static function () use ($shopModel, $u5): bool {
        try {
            $shopModel->crearItem((int) $u5, 'recompensa', 'Custom 16', null, 5);

            return false;
        } catch (ApiException $e) {
            return $e->httpStatus() === 409;
        }
    })());
    comprobar('un coste mayor de 100 se rechaza', (static function () use ($shopModel, $u5): bool {
        try {
            $shopModel->crearItem((int) $u5, 'recompensa', 'Caro', null, 101);

            return false;
        } catch (ApiException $e) {
            return $e->httpStatus() === 400;
        }
    })());

    $customActualizado = $shopModel->actualizarItem(
        (int) $u5,
        (int) $primerCustom['id'],
        'permiso',
        'Custom actualizado',
        'Nueva descripción',
        12
    );
    comprobarIgual('se actualiza el coste del artículo propio', 12, $customActualizado['coste']);
    $customBorrado = $shopModel->desactivarItem((int) $u5, (int) $primerCustom['id']);
    comprobar('el artículo propio se desactiva', $customBorrado['activo'] === false);
    comprobarIgual('al desactivar quedan catorce artículos propios', 14, $shopModel->getTienda((int) $u5)['personalizados']);

    $saldoAntesCompra = $balanceModel->getSaldoUsuario((int) $u5);
    $tareaCompra = $shopModel->crearTarea((int) $u5, (int) $itemsPorClave['pelicula']['id']);
    comprobarIgual('comprar una recompensa descuenta coins', $saldoAntesCompra - 15, $balanceModel->getSaldoUsuario((int) $u5));
    comprobarIgual('la compra queda pendiente', 'pendiente', $tareaCompra['estado']);
    comprobar('la compra genera un evento de tienda', (int) $db->query(
        "SELECT COUNT(*) FROM eventos WHERE origen = 'tienda' AND usuario_id = {$u5} AND anulado = FALSE"
    )->fetchColumn() === 1);
    comprobar('el solicitante no puede confirmar su compra', (static function () use ($shopModel, $u5, $tareaCompra): bool {
        try {
            $shopModel->confirmarTarea((int) $u5, (int) $tareaCompra['id']);

            return false;
        } catch (ApiException $e) {
            return $e->httpStatus() === 403;
        }
    })());

    $tareaConfirmada = $shopModel->confirmarTarea((int) $u6, (int) $tareaCompra['id']);
    comprobarIgual('la pareja confirma la compra', 'cumplida', $tareaConfirmada['estado']);
    comprobarIgual('confirmar una compra no vuelve a cobrar', $saldoAntesCompra - 15, $balanceModel->getSaldoUsuario((int) $u5));

    $saldoAntesRechazo = $balanceModel->getSaldoUsuario((int) $u5);
    $tareaRechazo = $shopModel->crearTarea((int) $u5, (int) $itemsPorClave['pelicula']['id']);
    comprobarIgual('el rechazo parte de un cargo', $saldoAntesRechazo - 15, $balanceModel->getSaldoUsuario((int) $u5));
    $tareaRechazada = $shopModel->rechazarTarea((int) $u6, (int) $tareaRechazo['id']);
    comprobarIgual('la tarea rechazada queda marcada', 'rechazada', $tareaRechazada['estado']);
    comprobarIgual('el rechazo devuelve las coins', $saldoAntesRechazo, $balanceModel->getSaldoUsuario((int) $u5));

    $saldoAntesCancel = $balanceModel->getSaldoUsuario((int) $u5);
    $tareaCancel = $shopModel->crearTarea((int) $u5, (int) $itemsPorClave['pelicula']['id']);
    $tareaCancelada = $shopModel->cancelarTarea((int) $u5, (int) $tareaCancel['id']);
    comprobarIgual('el solicitante puede cancelar', 'cancelada', $tareaCancelada['estado']);
    comprobarIgual('la cancelación devuelve las coins', $saldoAntesCancel, $balanceModel->getSaldoUsuario((int) $u5));

    $saldoAntesGanancia = $balanceModel->getSaldoUsuario((int) $u5);
    $tareaGanancia = $shopModel->crearTarea((int) $u5, (int) $itemsPorClave['ayuda_extra']['id']);
    comprobar('una tarea de ganancia no cobra al crearse', $tareaGanancia['evento_id'] === null);
    comprobarIgual('la ganancia no cambia el saldo al crearse', $saldoAntesGanancia, $balanceModel->getSaldoUsuario((int) $u5));
    $tareaGananciaConfirmada = $shopModel->confirmarTarea((int) $u6, (int) $tareaGanancia['id']);
    comprobarIgual('la ganancia se acredita al confirmar', $saldoAntesGanancia + 25, $balanceModel->getSaldoUsuario((int) $u5));
    comprobar('la ganancia genera un evento de tarea', (int) $db->query(
        "SELECT COUNT(*) FROM eventos WHERE origen = 'tarea' AND efecto = 'suma' AND coste = 25"
    )->fetchColumn() === 1);
    comprobarIgual('la tarea de ganancia queda cumplida', 'cumplida', $tareaGananciaConfirmada['estado']);

    echo "\n== Bonos de cumpleaños y aniversario ==\n";

    $hoyBonus = api_hoy()->format('Y-m-d');
    $aniversarioAnterior = api_hoy()->modify('-1 year')->format('Y-m-d');
    $userModel->updateBirthday((int) $u5, $hoyBonus);
    comprobarIgual('se guarda el cumpleaños', $hoyBonus, $userModel->getById((int) $u5)['fecha_nacimiento']);
    $parejaAniversario = $coupleModel->actualizarAniversario((int) $u5, $aniversarioAnterior);
    comprobarIgual('se guarda el aniversario', $aniversarioAnterior, $parejaAniversario['fecha_aniversario']);

    $saldoU5Antes = $balanceModel->getSaldoUsuario((int) $u5);
    $saldoU6Antes = $balanceModel->getSaldoUsuario((int) $u6);
    $bonosCreados = $bonusModel->procesar((int) $u5);
    comprobarIgual('se crean tres bonos (cumpleaños y dos aniversarios)', 3, count($bonosCreados));
    comprobarIgual('el cumpleaños y el aniversario suman 100 a u5', $saldoU5Antes + 100, $balanceModel->getSaldoUsuario((int) $u5));
    comprobarIgual('el aniversario suma 50 a u6', $saldoU6Antes + 50, $balanceModel->getSaldoUsuario((int) $u6));
    comprobarIgual('los bonos generan tres eventos', 3, (int) $db->query(
        "SELECT COUNT(*) FROM eventos WHERE origen = 'bono'"
    )->fetchColumn());

    $bonosRepetidos = $bonusModel->procesar((int) $u6);
    comprobarIgual('los bonos no se repiten el mismo año', 0, count($bonosRepetidos));
    comprobarIgual('el saldo no cambia al reprocesar', $saldoU5Antes + 100, $balanceModel->getSaldoUsuario((int) $u5));
    comprobarIgual('hay tres bonos registrados', 3, (int) $db->query('SELECT COUNT(*) FROM pareja_bonos')->fetchColumn());
} catch (Throwable $e) {
    $fallos++;
    echo "\nEXCEPCIÓN: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
} finally {
    limpiar($db);
}

echo "\n" . str_repeat('-', 50) . "\n";
echo "Pruebas: {$pruebas}  Fallos: {$fallos}\n";

exit($fallos === 0 ? 0 : 1);
