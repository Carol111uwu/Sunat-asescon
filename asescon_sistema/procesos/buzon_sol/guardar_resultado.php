<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/configuracion/conexion.php';
require_once dirname(__DIR__, 2) . '/modelos/buzones/BuzonSol.php';
require_once dirname(__DIR__, 2) . '/servicios/sunat/ServicioColaBuzonSol.php';

$idTrabajo = trim((string)($argv[1] ?? ''));
$codigoProceso = (int)($argv[2] ?? 1);
if ($idTrabajo === '') {
    exit(2);
}

$cola = new ServicioColaBuzonSol();
$rutaSolicitud = $cola->rutaSolicitudProcesando($idTrabajo);
$rutaResultado = $cola->rutaResultadoTemporal($idTrabajo);
$solicitud = [];
if (is_file($rutaSolicitud)) {
    $contenidoSolicitud = @file_get_contents($rutaSolicitud);
    $decodificado = is_string($contenidoSolicitud) ? json_decode($contenidoSolicitud, true) : null;
    $solicitud = is_array($decodificado) ? $decodificado : [];
}
$idCliente = (int)($solicitud['id_cliente'] ?? 0);
if ($idCliente <= 0) {
    $cola->limpiarTrabajo($idTrabajo);
    exit(3);
}

$codigoSalida = 0;
try {
    $contenido = is_file($rutaResultado) ? (string)file_get_contents($rutaResultado) : '';
    $respuesta = json_decode(trim($contenido), true);

    if (!is_array($respuesta)) {
        $mensaje = $codigoProceso === 124
            ? 'SUNAT SOL no respondió dentro del tiempo permitido.'
            : 'El conector del Buzón SOL terminó sin una respuesta válida.';
        $cola->fallar($idCliente, $idTrabajo, $mensaje);
        $codigoSalida = 4;
    } elseif (!($respuesta['ok'] ?? false)) {
        $mensaje = trim((string)($respuesta['mensaje'] ?? 'No se pudo acceder al Buzón SOL.'));
        $cola->fallar($idCliente, $idTrabajo, $mensaje);
        $codigoSalida = 5;
    } else {
        $datos = $respuesta['data'] ?? null;
        if (!is_array($datos)) {
            $cola->fallar($idCliente, $idTrabajo, 'SUNAT devolvió un formato inesperado para el Buzón SOL.');
            $codigoSalida = 6;
        } else {
            if(empty($datos['sol_validado']))throw new RuntimeException('La respuesta no confirmó el acceso SOL.');
            $s=$conexion->prepare('SELECT ruc FROM clientes WHERE id_cliente=? LIMIT 1');$s->bind_param('i',$idCliente);$s->execute();$clienteResultado=$s->get_result()->fetch_assoc();$s->close();
            if(!$clienteResultado||(string)($datos['ruc']??'')!==(string)$clienteResultado['ruc'])throw new RuntimeException('La respuesta no corresponde al cliente solicitado.');
            if(is_array($datos['detalle']??null)){
                $detalle=$datos['detalle'];$idDocumento=(int)($solicitud['id_documento']??0);
                if(($solicitud['modo_documento']??'')!=='DETALLE'||$idDocumento<1||(int)($detalle['id_documento']??0)!==$idDocumento)throw new RuntimeException('El detalle no corresponde a la solicitud autorizada.');
                require_once dirname(__DIR__,2).'/servicios/sunat/RepositorioDetalleOficialSol.php';
                (new RepositorioDetalleOficialSol($conexion))->guardar($idCliente,$detalle);
                $cola->completarDetalle($idCliente,$idTrabajo);
                $cola->limpiarTrabajo($idTrabajo);exit(0);
            }
            if(is_array($datos['documento']??null)){
                require_once dirname(__DIR__,2).'/servicios/archivos/ServicioArchivosPrivados.php';
                require_once dirname(__DIR__,2).'/modelos/buzones/RepositorioDocumentoBuzon.php';
                $documentoResultado=$datos['documento'];$idDocumento=(int)($solicitud['id_documento']??0);
                if($idDocumento<1||(int)($documentoResultado['id_documento']??0)!==$idDocumento)throw new RuntimeException('El PDF no corresponde a la solicitud.');
                $archivosPrivados=new ServicioArchivosPrivados();$archivoResultado=$archivosPrivados->resolver((string)($documentoResultado['archivo']??''),'SOL');
                $directorioEsperado=realpath(asesconRutaAlmacenamiento().'/sunat/buzon_sol/adjuntos/'.$idCliente.'/'.$idDocumento);
                if(!$archivoResultado||!$directorioEsperado||!str_starts_with($archivoResultado,$directorioEsperado.DIRECTORY_SEPARATOR))throw new RuntimeException('La ruta del PDF no es válida para este registro.');
                $referencia=$archivosPrivados->guardarArchivo($archivoResultado,'SOL',$idCliente,$idDocumento);
                (new RepositorioDocumentoBuzon($conexion))->vincular('SOL',$idDocumento,$referencia,'c.id_cliente='.(int)$idCliente);
                if(is_file($archivoResultado)&&basename($archivoResultado)!==basename($referencia))unlink($archivoResultado);
                $cola->completarDocumento($idCliente,$idTrabajo);
                $cola->limpiarTrabajo($idTrabajo);exit(0);
            }
            if(!is_array($datos['notificaciones']??null))throw new RuntimeException('La respuesta no contiene una lista de notificaciones.');
            $documentos=$datos['notificaciones'];$conexion->begin_transaction();
            try{$nuevos=(new BuzonSol($conexion))->guardarSincronizados($idCliente,$documentos);$conexion->commit();}
            catch(Throwable $errorGuardado){$conexion->rollback();throw $errorGuardado;}
            $totalDocumentos=count($documentos);
            if(!empty($solicitud['solo_adjuntos'])){$s=$conexion->prepare('SELECT COUNT(*) n FROM buzon_sol_sunat WHERE id_cliente=?');$s->bind_param('i',$idCliente);$s->execute();$totalDocumentos=(int)$s->get_result()->fetch_assoc()['n'];$s->close();}
            $pdfGuardados=0;
            $pdfPendientes=!empty($datos['advertencia_adjuntos']) ? 1 : 0;
            try {
                require_once dirname(__DIR__,2).'/servicios/sunat/ServicioResultadoAdjuntosSol.php';
                $entrada=json_decode((string)file_get_contents($cola->rutaEntradaTemporal($idTrabajo)),true,512,JSON_THROW_ON_ERROR);
                $pdfGuardados=(new ServicioResultadoAdjuntosSol($conexion))->guardar($idCliente,$datos['adjuntos']??[],$entrada);
                require_once dirname(__DIR__,2).'/servicios/sunat/ServicioPrecargaAdjuntosSol.php';
                $pdfPendientes=max($pdfPendientes,count((new ServicioPrecargaAdjuntosSol($conexion))->pendientes($idCliente,false,0)));
            } catch (Throwable $errorAdjuntos) {
                                                                           
                                                                        
                if (!empty($solicitud['solo_adjuntos'])) throw $errorAdjuntos;
                $pdfPendientes=max(1,$pdfPendientes);
                error_log('Buzón SOL: error opcional de PDF después de guardar bandeja (' . get_class($errorAdjuntos) . ').');
            }
            $cola->completar($idCliente, $idTrabajo, $totalDocumentos, $nuevos);
            $cola->completarAdjuntos($idCliente,$idTrabajo,$pdfGuardados,$pdfPendientes);
            try {
                require_once dirname(__DIR__, 2) . '/modelos/comunicaciones/ColaAvisosBuzones.php';
                (new ColaAvisosBuzones($conexion))->registrar('SUNAT', $idCliente);
            } catch (Throwable $errorAviso) {
                error_log('Avisos SUNAT: no se pudo registrar la cola de correo.');
            }
        }
    }
} catch (Throwable $e) {
    error_log('Buzón SOL guardar cron: ' . get_class($e) . ': ' . $e->getMessage());
    $cola->fallar($idCliente, $idTrabajo, 'No se pudo guardar la respuesta del Buzón SOL. Consulta el registro de errores.');
    $codigoSalida = 7;
} finally {
    $cola->limpiarTrabajo($idTrabajo);
}

exit($codigoSalida);
