<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../servicios/sunat/ServicioPreparacionLiquidacion.php';
asesconExigirLogin();header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
try{
    if(!asesconTienePermiso($conexion,'LIQUIDACIONES','ver')){http_response_code(403);echo json_encode(['ok'=>false,'mensaje'=>'No tienes permiso para ver liquidaciones.']);exit;}
    $id=(int)($_GET['id_cliente']??0);if(!asesconPuedeVerCliente($conexion,$id)){http_response_code(403);echo json_encode(['ok'=>false,'mensaje'=>'Cliente no autorizado.']);exit;}
    $datos=(new ServicioPreparacionLiquidacion($conexion))->preparar($id,(string)($_GET['periodo']??''),!empty($_GET['recargar']));$datos['puede_editar']=asesconTienePermiso($conexion,'LIQUIDACIONES','editar')&&asesconPuedeGestionarCliente($conexion,$id);
    echo json_encode(['ok'=>true,'data'=>$datos],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
}catch(DomainException $e){http_response_code(400);echo json_encode(['ok'=>false,'mensaje'=>$e->getMessage()]);}
catch(Throwable $e){error_log('Liquidación: '.get_class($e));http_response_code(500);echo json_encode(['ok'=>false,'mensaje'=>'No se pudo preparar la liquidación.']);}
