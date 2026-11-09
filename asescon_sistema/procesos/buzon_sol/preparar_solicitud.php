<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/configuracion/conexion.php';
require_once dirname(__DIR__, 2) . '/servicios/sunat/CargadorCredencialesSunat.php';
require_once dirname(__DIR__, 2) . '/servicios/sunat/ServicioColaBuzonSol.php';

$cola = new ServicioColaBuzonSol();
$solicitud = $cola->tomarSiguienteSolicitud();
if ($solicitud === null) {
    exit(0);
}

$idTrabajo = (string)$solicitud['id_trabajo'];
$idCliente = (int)$solicitud['id_cliente'];

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
        'ruc' => (string)$credenciales['ruc'],
        'usuario_sol' => (string)$credenciales['usuario_sol'],
        'clave_sol' => (string)$credenciales['clave_sol'],
    ];

    if((int)($solicitud['id_documento']??0)>0){
        $idDocumento=(int)$solicitud['id_documento'];$s=$conexion->prepare('SELECT id_documento,identificador_externo,asunto,tipo_documento,observaciones,COALESCE(fecha_recepcion,fecha_documento) fecha FROM buzon_sol_sunat WHERE id_documento=? AND id_cliente=? LIMIT 1');$s->bind_param('ii',$idDocumento,$idCliente);$s->execute();$documento=$s->get_result()->fetch_assoc();$s->close();
        if(!$documento)throw new RuntimeException('La notificación solicitada ya no está disponible.');
        $entrada['documento']=$documento;$entrada['id_trabajo']=$idTrabajo;
        $entrada['modo_documento']=($solicitud['modo_documento']??'PDF')==='DETALLE'?'DETALLE':'PDF';
        $entrada['ruta_progreso']=$cola->rutaProgresoTemporal($idTrabajo);
        $entrada['directorio_adjuntos']=asesconRutaAlmacenamiento().'/sunat/buzon_sol/adjuntos/'.$idCliente.'/'.$idDocumento;
    }
    $entrada['id_trabajo']=$idTrabajo;
    $entrada['ruta_progreso']=$cola->rutaProgresoTemporal($idTrabajo);
    if (empty($entrada['documento'])) {
        $entrada['documentos_adjuntos'] = [];
        try {
            require_once dirname(__DIR__,2).'/servicios/sunat/ServicioPrecargaAdjuntosSol.php';
            $entrada['documentos_adjuntos']=(new ServicioPrecargaAdjuntosSol($conexion))->pendientes($idCliente,true,20);
        } catch (Throwable $errorAdjuntos) {
            if (!empty($solicitud['solo_adjuntos'])) throw $errorAdjuntos;
            error_log('Buzón SOL: falló la preparación opcional de PDF (' . get_class($errorAdjuntos) . '). La consulta de notificaciones seguirá.');
        }
        foreach ($entrada['documentos_adjuntos'] as &$adjunto) {
            $adjunto['directorio_adjuntos']=asesconRutaAlmacenamiento().'/sunat/buzon_sol/adjuntos/'.$idCliente.'/'.$adjunto['id_documento'];
        }
        unset($adjunto);
        $entrada['solo_adjuntos']=!empty($solicitud['solo_adjuntos']);
    }
    $json = json_encode($entrada, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('No se pudo preparar la entrada del conector.');
    }

    $rutaEntrada = $cola->rutaEntradaTemporal($idTrabajo);
    $rutaResultado = $cola->rutaResultadoTemporal($idTrabajo);
    if (file_put_contents($rutaEntrada, $json, LOCK_EX) === false) {
        throw new RuntimeException('No se pudo preparar el trabajo del Buzón SOL.');
    }
    @chmod($rutaEntrada, 0600);

    echo $idTrabajo . "\t" . $rutaEntrada . "\t" . $rutaResultado;
} catch (Throwable $e) {
    error_log('Buzón SOL preparar cron: ' . get_class($e) . ': ' . $e->getMessage());
    $cola->fallar($idCliente, $idTrabajo, 'No se pudo preparar la consulta del cliente. Consulta el registro de errores.');
    $cola->limpiarTrabajo($idTrabajo);
    exit(1);
}
