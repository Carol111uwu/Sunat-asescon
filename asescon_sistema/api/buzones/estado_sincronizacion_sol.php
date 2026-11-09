<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../servicios/sunat/ServicioColaBuzonSol.php';
asesconExigirLogin();header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
try{
    if(!asesconTienePermiso($conexion,'BUZON_SOL','ver')){http_response_code(403);throw new DomainException('No tienes acceso al Buzón SOL.');}
    $id=(int)($_GET['id_cliente']??0);
    if($id<1||!asesconPuedeVerCliente($conexion,$id)){http_response_code(403);throw new DomainException('Cliente no autorizado.');}
    session_write_close();$estado=(new ServicioColaBuzonSol())->obtenerEstadoCliente($id);
    echo json_encode(['ok'=>true,'estado'=>$estado],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(DomainException $e){if(http_response_code()<400)http_response_code(422);echo json_encode(['ok'=>false,'mensaje'=>$e->getMessage()]);}
catch(Throwable $e){http_response_code(503);echo json_encode(['ok'=>false,'mensaje'=>'No se pudo comprobar el estado de la consulta.']);}
