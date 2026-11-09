<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../servicios/sunat/PortalesSunat.php';
require_once __DIR__.'/../../servicios/sunat/CargadorCredencialesSunat.php';
asesconExigirLogin();header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, private');header('Pragma: no-cache');header('Referrer-Policy: no-referrer');header('X-Content-Type-Options: nosniff');
try{
 if(empty($_SERVER['HTTPS'])||strtolower((string)$_SERVER['HTTPS'])==='off'){http_response_code(403);throw new DomainException('El acceso con credenciales requiere HTTPS.');}
 if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||!asesconValidarCsrf((string)($_POST['csrf_token']??''),'csrf_acceso_portal')){http_response_code(403);throw new DomainException('Solicitud de acceso no válida.');}
 $id=(int)($_POST['id_cliente']??0);if(!PortalesSunat::puedeAcceder($conexion,$id)){http_response_code(403);throw new DomainException('No puedes utilizar las credenciales de este cliente.');}
 $destino=PortalesSunat::destino((string)($_POST['destino']??'declaraciones'));$datos=(new CargadorCredencialesSunat($conexion))->obtenerSolPorCliente($id);
 asesconRegistrarAuditoria($conexion,'SOLICITAR_ACCESO_LOCAL','SUNAT',$id,'clientes',(string)$id,'Destino oficial: '.$destino['nombre']);
 echo json_encode(['ok'=>true,'data'=>$datos+['url'=>$destino['url'],'vence_en'=>time()+90]],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
}catch(Throwable $e){if(http_response_code()<400)http_response_code(400);error_log('Acceso local SUNAT: '.get_class($e));echo json_encode(['ok'=>false,'mensaje'=>$e instanceof DomainException?$e->getMessage():'No se pudo preparar el acceso. Revisa las credenciales SOL guardadas.'],JSON_UNESCAPED_UNICODE);}
