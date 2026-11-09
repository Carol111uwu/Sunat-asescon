<?php

declare(strict_types=1);

$nivelBuffer = ob_get_level();
ob_start();
require_once __DIR__ . '/../../configuracion/inicializacion.php';
require_once __DIR__ . '/../../controladores/sunat/ControladorExportacionSire.php';

try {
    asesconExigirLogin();
    $resultado = (new ControladorExportacionSire($conexion))->generar($_GET);
    while (ob_get_level() > $nivelBuffer) ob_end_clean();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $resultado['nombre'] . '"');
    header('Content-Length: ' . strlen($resultado['contenido']));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    header('X-Content-Type-Options: nosniff');
    echo $resultado['contenido'];
    exit;
} catch (Throwable $e) {
    while (ob_get_level() > $nivelBuffer) ob_end_clean();
    http_response_code($e instanceof InvalidArgumentException ? 422 : 400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No se pudo generar el Excel SIRE: ' . $e->getMessage();
}
