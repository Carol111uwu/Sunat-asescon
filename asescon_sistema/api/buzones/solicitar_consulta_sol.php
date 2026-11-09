<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../servicios/sunat/ServicioColaBuzonSol.php';
asesconExigirLogin();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
function consultaSolRespuesta(int $codigo,bool $ok,string $mensaje,array $datos=[]):never {
    http_response_code($codigo);
    echo json_encode(['ok'=>$ok,'mensaje'=>$mensaje,'data'=>$datos],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')consultaSolRespuesta(405,false,'Método no permitido.');
if(!asesconValidarCsrf((string)($_POST['csrf_token']??''),'csrf_buzon_sol'))consultaSolRespuesta(403,false,'La sesión venció. Actualiza la página.');
if(!asesconTienePermiso($conexion,'BUZON_SOL','editar'))consultaSolRespuesta(403,false,'No tienes permiso para consultar el Buzón SOL.');
try {
    $idCliente=(int)($_POST['id_cliente']??0);
    if($idCliente<1||!asesconPuedeVerCliente($conexion,$idCliente))consultaSolRespuesta(403,false,'Selecciona un cliente autorizado.');
    $s=$conexion->prepare("SELECT id_cliente FROM clientes WHERE id_cliente=? AND estado='ACTIVO' LIMIT 1");
    $s->bind_param('i',$idCliente);$s->execute();$activo=$s->get_result()->fetch_assoc();$s->close();
    if(!$activo)consultaSolRespuesta(404,false,'El cliente no está activo.');
    $estado=(new ServicioColaBuzonSol())->solicitar($idCliente,(int)($_SESSION['id_usuario']??0));
    consultaSolRespuesta(200,true,'Consulta registrada. Se procesará desde el servidor.',[
        'estado'=>(string)($estado['estado']??'PENDIENTE'),'id_cliente'=>$idCliente,
    ]);
}catch(DomainException $e){consultaSolRespuesta(409,false,$e->getMessage());}
catch(Throwable $e){error_log('Consulta asincrónica SOL: '.get_class($e));consultaSolRespuesta(500,false,'No se pudo registrar la consulta. Revisa el estado de Cron y la conexión.');}
