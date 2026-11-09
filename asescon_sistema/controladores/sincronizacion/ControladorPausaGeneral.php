<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../servicios/sincronizacion/ColaNocturna.php';
asesconExigirLogin();
if(!asesconEsPrincipal($conexion)){http_response_code(403);exit('Solo la cuenta principal puede controlar la cola.');}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);exit('Método no permitido.');}
if(!asesconValidarCsrf((string)($_POST['csrf_token']??''),'csrf_pausa_sync')){
    http_response_code(403);exit('Sesión vencida. Recarga la página.');
}
$accion=(string)($_POST['accion']??'');
if(!in_array($accion,['pausar','reanudar'],true)){http_response_code(400);exit('Acción no permitida.');}
try{
    (new ColaNocturna())->establecerPausa($accion==='pausar');
    $_SESSION['mensaje_sync_general']=['tipo'=>'ok','texto'=>$accion==='pausar'
        ?'Sincronización en pausa: no se tomarán nuevos trabajos. Los que ya estaban ejecutándose pueden finalizar. Ningún dato guardado se borra.'
        :'Sincronización reanudada. Cron continuará procesando los trabajos pendientes sin borrar información previa.'];
}catch(Throwable $e){
    error_log('Pausa sincronizacion '.get_class($e));
    $_SESSION['mensaje_sync_general']=['tipo'=>'error','texto'=>'No se pudo modificar la pausa. Revisa el almacenamiento privado.'];
}
asesconRedirigir('vistas/utilitarios/sincronizacion.php');
