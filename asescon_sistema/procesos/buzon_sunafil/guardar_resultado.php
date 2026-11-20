<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/configuracion/conexion.php';
require_once dirname(__DIR__, 2) . '/modelos/buzones/BuzonSunafil.php';
require_once dirname(__DIR__, 2) . '/servicios/archivos/ServicioArchivosPrivados.php';
require_once dirname(__DIR__, 2) . '/servicios/sunafil/ServicioColaBuzonSunafil.php';

$idTrabajo = trim((string)($argv[1] ?? ''));
$codigoProceso = (int)($argv[2] ?? 1);
if ($idTrabajo === '') {
    exit(2);
}

$cola = new ServicioColaBuzonSunafil();
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
            ? 'SUNAFIL no respondió dentro del tiempo permitido.'
            : 'El conector del Buzón SUNAFIL terminó sin una respuesta válida.';
        $cola->fallar($idCliente, $idTrabajo, $mensaje);
        $codigoSalida = 4;
    } elseif (!($respuesta['ok'] ?? false)) {
        $mensaje = trim((string)($respuesta['mensaje'] ?? 'No se pudo acceder al Buzón SUNAFIL.'));
        $codigoPublico=strtoupper((string)($respuesta['codigo']??'SUNAFIL_CONSULTA_FALLIDA'));
        if(!preg_match('/^[A-Z0-9_]{3,60}$/D',$codigoPublico))$codigoPublico='SUNAFIL_CONSULTA_FALLIDA';
        $cola->fallar($idCliente, $idTrabajo, '['.$codigoPublico.'] '.$mensaje);
        $codigoSalida = 5;
    } else {
        $datos = $respuesta['data'] ?? null;
        if (!is_array($datos) || !($datos['sunafil_validado'] ?? false) || !is_array($datos['notificaciones'] ?? null) || $codigoProceso !== 0) {
            $cola->fallar($idCliente, $idTrabajo, 'SUNAFIL devolvió un formato inesperado para el Buzón SUNAFIL.');
            $codigoSalida = 6;
        } else {
            $consulta = $conexion->prepare('SELECT ruc FROM clientes WHERE id_cliente=? LIMIT 1');
            if (!$consulta) { throw new RuntimeException('No se pudo verificar el cliente.'); }
            $consulta->bind_param('i', $idCliente);
            $consulta->execute();
            $cliente = $consulta->get_result()->fetch_assoc();
            $consulta->close();
            if (!$cliente || (string)($datos['ruc'] ?? '') !== (string)$cliente['ruc']) {
                throw new RuntimeException('La respuesta no corresponde al cliente solicitado.');
            }
            $idDocumento = (int)($solicitud['id_documento'] ?? 0);
            if ($idDocumento > 0) {
                $documento = $datos['documento'] ?? null;
                if (!is_array($documento) || (int)($documento['id_documento'] ?? 0) !== $idDocumento) {
                    throw new RuntimeException('SUNAFIL no devolvió el documento solicitado.');
                }
                (new BuzonSunafil($conexion))->vincularPdfOficial(
                    $idCliente,
                    $idDocumento,
                    (string)($documento['identificador_externo'] ?? ''),
                    (string)($documento['archivo'] ?? '')
                );
                $cola->completarPdf($idCliente, $idTrabajo);
            } else {
                $documentos = is_array($datos['notificaciones'] ?? null) ? $datos['notificaciones'] : [];
                $archivos = new ServicioArchivosPrivados();
                foreach ($documentos as &$documento) {
                    if (!is_array($documento)) {
                        continue;
                    }
                    $identificador = (string) ($documento['identificador_externo'] ?? '');
                    $referencia = (string) ($documento['archivo'] ?? '');
                    $esperada = ServicioArchivosPrivados::CARPETAS['SUNAFIL'] . "/{$idCliente}/oficial/{$identificador}.pdf";
                    $documento['archivo'] = preg_match('/^SUNAFIL-[a-f0-9]{40}$/D', $identificador)
                        && $referencia === $esperada && $archivos->resolver($referencia, 'SUNAFIL') ? $referencia : '';
                }
                unset($documento);
                require_once dirname(__DIR__, 2) . '/servicios/buzones/RegistroNovedadesBuzones.php';
                $avisadorBuzones = new RegistroNovedadesBuzones($conexion);
                $marcadorAnterior = $avisadorBuzones->marcadorAnterior('SUNAFIL',$idCliente);
                $conexion->begin_transaction();
                try {
                    $nuevos = (new BuzonSunafil($conexion))->guardarSincronizados($idCliente, $documentos);
                    $conexion->commit();
                } catch (Throwable $e) {
                    $conexion->rollback();
                    throw $e;
                }
                if (empty($datos['categorias_no_verificadas'])) {
                    try { $avisadorBuzones->registrarExito('SUNAFIL',$idCliente,$marcadorAnterior); }
                    catch(Throwable $errorAvisos) { error_log('Avisos nuevos SUNAFIL: '.get_class($errorAvisos)); }
                }
                $cola->completar($idCliente, $idTrabajo, count($documentos), $nuevos, (array)($datos['categorias_no_verificadas'] ?? []));
                try {
                    require_once dirname(__DIR__, 2) . '/modelos/comunicaciones/ColaAvisosBuzones.php';
                    (new ColaAvisosBuzones($conexion))->registrar('SUNAFIL', $idCliente);
                } catch (Throwable $errorAviso) {
                    error_log('Avisos SUNAFIL: no se pudo registrar la cola de correo.');
                }
            }
        }
    }
} catch (Throwable $e) {
    error_log('Buzón SUNAFIL guardar cron: ' . get_class($e) . ': ' . $e->getMessage());
    $cola->fallar($idCliente, $idTrabajo, 'No se pudo guardar la respuesta del Buzón SUNAFIL. Consulta el registro de errores.');
    $codigoSalida = 7;
} finally {
    $cola->limpiarTrabajo($idTrabajo);
}

exit($codigoSalida);
