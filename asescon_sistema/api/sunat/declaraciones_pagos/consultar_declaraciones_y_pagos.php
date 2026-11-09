<?php
declare(strict_types=1);
require_once __DIR__.'/../../../configuracion/inicializacion.php';
require_once __DIR__.'/../../../controladores/sunat/declaraciones_pagos/ControladorConsultaDeclaraciones.php';
header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');
asesconExigirPermiso($conexion,'DECLARACIONES_PAGOS_SUNAT','ver');
try {
    if (($_SERVER['REQUEST_METHOD']??'')!=='POST') {http_response_code(405);throw new DomainException('Usa el formulario de consulta de ASESCON.');}
    if (!asesconValidarCsrf((string)($_POST['csrf_token']??''),'csrf_consulta_oficial')) {http_response_code(403);throw new DomainException('La sesión del formulario venció. Recarga la página.');}
    $https=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
    if (!$https && !in_array($_SERVER['SERVER_NAME']??'',['localhost','127.0.0.1'],true)) {http_response_code(403);throw new DomainException('La consulta requiere HTTPS.');}
    @set_time_limit(120);
    $r=(new ControladorConsultaDeclaraciones($conexion))->ejecutar($_POST);
    if (isset($r['archivo'])) {
        $a=$r['archivo'];header('Content-Type: '.$a['tipo']);header('Content-Disposition: attachment; filename="'.$a['nombre'].'"');
        header('Content-Length: '.strlen($a['body']));echo $a['body'];exit;
    }
    header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>true]+$r,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    unset($_SESSION['consulta_oficial_sol'][(int)($_POST['id_cliente']??0)]);
    if (http_response_code()<400) http_response_code($e instanceof DomainException?422:500);
    header('Content-Type: application/json; charset=utf-8');
    if (!($e instanceof DomainException)) error_log('ASESCON consulta declaraciones: '.get_class($e));
    echo json_encode(['ok'=>false,'mensaje'=>$e instanceof DomainException?$e->getMessage():'No se pudo guardar o completar la consulta. Se conserva el historial anterior.'],JSON_UNESCAPED_UNICODE);
}
