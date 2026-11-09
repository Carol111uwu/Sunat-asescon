<?php

declare(strict_types=1);

require_once __DIR__ . '/../../configuracion/respuesta_api.php';
asesconPrepararRespuestaApi();
require_once __DIR__ . '/../../configuracion/inicializacion.php';
require_once __DIR__ . '/../../controladores/sunat/ControladorOperacionesSire.php';
require_once __DIR__ . '/../../servicios/sunat/ExcepcionSunat.php';

$candadoSire = null;
try {
    asesconExigirLogin();

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        http_response_code(405);
        throw new RuntimeException('Método HTTP no permitido.');
    }

    $input = $_POST;
    if (str_contains(strtolower((string)($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json')) {
        $input = json_decode(
            (string)file_get_contents('php://input'),
            true,
            32,
            JSON_THROW_ON_ERROR
        );
        if (!is_array($input)) {
            throw new InvalidArgumentException('Entrada JSON inválida.');
        }
    }

    $tokenCsrf = trim((string)(
        $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? $input['csrf_token']
        ?? ''
    ));
    if (!asesconValidarCsrf($tokenCsrf, 'csrf_sire')) {
        http_response_code(403);
        throw new RuntimeException('Token CSRF inválido o vencido.');
    }

    $accion = strtolower(trim((string)($input['accion'] ?? '')));
    $idCliente = (int)($input['id_cliente'] ?? 0);
    $libro = trim((string)($input['libro'] ?? ''));
    $periodo = preg_replace('/\D+/', '', (string)($input['periodo'] ?? '')) ?? '';
    $ticket = trim((string)($input['ticket'] ?? ''));

                                                                      
                                                                             
    if (in_array($accion, ['solicitar','descargar','reprocesar'], true)) {
        require_once __DIR__ . '/../../configuracion/rutas_privadas.php';
        $sincronizacion = asesconRutaAlmacenamiento().'/sincronizacion';
        if (!is_dir($sincronizacion) && !mkdir($sincronizacion,0700,true) && !is_dir($sincronizacion)) {
            throw new RuntimeException('No se pudo preparar la sincronización SIRE.');
        }
        if (is_file($sincronizacion.'/mantenimiento.flag')) {
            http_response_code(503);
            throw new RuntimeException('Mantenimiento diario en curso.');
        }
        $candadoSire = fopen($sincronizacion.'/consultas_sire.lock','c+');
        if (!$candadoSire || !flock($candadoSire,LOCK_SH|LOCK_NB)
            || is_file($sincronizacion.'/mantenimiento.flag')) {
            http_response_code(503);
            throw new RuntimeException('Mantenimiento diario en curso.');
        }
    }

    if ($accion === 'inventario') {
        $libro = strtoupper($libro);
        if (!in_array($libro, ['RVIE', 'RCE'], true)) {
            throw new InvalidArgumentException('Libro SIRE inválido.');
        }
        asesconExigirPermiso($conexion, $libro, 'ver');
        require_once __DIR__ . '/../../modelos/sunat/RepositorioLecturaSire.php';
        $repo = new RepositorioLecturaSire($conexion);
        $data = ['periodos' => $repo->listarPeriodosGuardados($libro, asesconFiltroClientesSql($conexion, 'c'))];
    } else {
    $controlador = new ControladorOperacionesSire($conexion);
    $data = match ($accion) {
        'periodos' => $controlador->periodos($idCliente, $libro),
        'solicitar' => $controlador->solicitar($idCliente, $libro, $periodo),
        'ticket_reciente' => $controlador->ticketReciente($idCliente, $libro, $periodo),
        'estado' => $controlador->estado($idCliente, $libro, $periodo, $ticket),
        'inconsistencias' => $controlador->inconsistencias($idCliente, $libro, $periodo, (int)($input['tipo_resumen'] ?? 1)),
        'descargar' => $controlador->descargar($idCliente, $libro, $periodo, $ticket),
        'reprocesar' => $controlador->reprocesarLocal($idCliente, $libro, $periodo),
        default => throw new InvalidArgumentException('Acción SIRE no permitida.'),
    };
    }

    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (JsonException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'mensaje' => 'Entrada JSON inválida.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (ExcepcionSunat $e) {
    $status = $e->getHttpStatus();
    if ($status === null || $status < 400 || $status > 599) {
        $status = 400;
    }
    http_response_code($status);
    echo json_encode([
        'ok' => false,
        'mensaje' => $e->getMessage(),
        'codigo_sunat' => $e->getCodigoSunat(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    $status = http_response_code();
    if ($status < 400 || $status > 599) {
        $status = 500;
        http_response_code($status);
    }
    error_log('ASESCON SIRE: ' . get_class($e) . ' - ' . $e->getMessage());
    $mensaje = match ($status) {
        403 => 'No tienes permiso para ejecutar esta operación SIRE.',
        405 => 'Método HTTP no permitido.',
        503 => 'ASESCON está limpiando la caché del día anterior. Vuelve a consultar en unos minutos.',
        default => 'No se pudo completar la operación SIRE. Revisa el registro de errores PHP del servidor.',
    };
    echo json_encode(['ok' => false, 'mensaje' => $mensaje], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} finally {
    if (is_resource($candadoSire)) {
        flock($candadoSire,LOCK_UN);
        fclose($candadoSire);
    }
}
