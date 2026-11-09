<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/configuracion/conexion.php';
require_once dirname(__DIR__, 2) . '/servicios/sunat/CargadorCredencialesSunat.php';
require_once dirname(__DIR__, 2) . '/servicios/sunafil/ServicioColaBuzonSunafil.php';

$cola = new ServicioColaBuzonSunafil();
$solicitud = $cola->tomarSiguienteSolicitud();
if ($solicitud === null) {
    exit(0);
}

$idTrabajo = (string)$solicitud['id_trabajo'];
$idCliente = (int)$solicitud['id_cliente'];
date_default_timezone_set('America/Lima');

try {
    $stmt = $conexion->prepare('SELECT ruc FROM clientes WHERE id_cliente=? AND estado=\'ACTIVO\' LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('No se pudo preparar la consulta del cliente.');
    }
    $stmt->bind_param('i', $idCliente);
    $stmt->execute();
    $cliente = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$cliente) {
        throw new RuntimeException('El cliente ya no está disponible para sincronizar.');
    }

                                                                      
                                                                             
    $credenciales = (new CargadorCredencialesSunat($conexion))->obtenerSolPorCliente($idCliente);
    $entrada = [
        'modo' => 'bandeja',
        'ruc' => (string)$credenciales['ruc'],
        'usuario_sol' => (string)$credenciales['usuario_sol'],
        'clave_sol' => (string)$credenciales['clave_sol'],
    ];

    $json = json_encode($entrada, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('No se pudo preparar la entrada del conector.');
    }

    $rutaEntrada = $cola->rutaEntradaTemporal($idTrabajo);
    $rutaResultado = $cola->rutaResultadoTemporal($idTrabajo);
    if (file_put_contents($rutaEntrada, $json, LOCK_EX) === false) {
        throw new RuntimeException('No se pudo preparar el trabajo del Buzón SUNAFIL.');
    }
    @chmod($rutaEntrada, 0600);

    echo $idTrabajo . "\t" . $rutaEntrada . "\t" . $rutaResultado;
} catch (Throwable $e) {
    error_log('Buzón SUNAFIL preparar cron: ' . get_class($e) . ': ' . $e->getMessage());
    $cola->fallar($idCliente, $idTrabajo, 'No se pudo preparar la consulta del cliente. Consulta el registro de errores.');
    $cola->limpiarTrabajo($idTrabajo);
    exit(1);
}
