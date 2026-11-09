<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../servicios/sincronizacion/PlanificadorSincronizacion.php';
require_once __DIR__.'/../../servicios/sincronizacion/ColaNocturna.php';
asesconExigirLogin();
if(!asesconEsPrincipal($conexion)){http_response_code(403);exit('Solo la cuenta principal puede programar la sincronización general.');}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);exit('Método no permitido.');}
if(!asesconValidarCsrf((string)($_POST['csrf_token']??''),'csrf_sync_general')){http_response_code(403);exit('Sesión vencida. Recarga la página.');}
$volverDashboard=(string)($_POST['origen']??'')==='dashboard';

$base=asesconRutaAlmacenamiento().'/sincronizacion';
try{
    if((new ColaNocturna())->estaPausada())throw new DomainException('La sincronización está en pausa. Reanúdala antes de solicitar otro lote.');
    $relojLima=(int)(new DateTimeImmutable('now',new DateTimeZone('America/Lima')))->format('Hi');
    if(($relojLima>=30 && $relojLima<200) || is_file($base.'/mantenimiento.flag'))
        throw new DomainException('Mantenimiento diario en curso. Podrás consultar nuevamente después de las 02:00 (hora de Perú).');
    if(!is_dir($base)&&!mkdir($base,0700,true)&&!is_dir($base))throw new RuntimeException('No se pudo preparar la carpeta de trabajos.');
    $ruta=$base.'/solicitudes_manuales.lock';$f=fopen($ruta,'c+');
    if(!$f||!flock($f,LOCK_EX))throw new RuntimeException('Hay otra solicitud en proceso.');
    try{
        chmod($ruta,0600);
        rewind($f);$ultima=(int)trim((string)stream_get_contents($f));
        if($ultima>time()-900)throw new DomainException('Ya se solicitó una sincronización general recientemente. Espera 15 minutos antes de repetirla.');
                                                                        
                                                                
        $resultado=(new PlanificadorSincronizacion($conexion))->programar(true);
        ftruncate($f,0);rewind($f);fwrite($f,(string)time());fflush($f);
        $_SESSION['mensaje_sync_general']=['tipo'=>'ok','texto'=>'Se prepararon '.$resultado['trabajos'].' trabajos nuevos para '.$resultado['clientes'].' clientes. Los libros SIRE usarán solo el mes actual.'];
    }finally{flock($f,LOCK_UN);fclose($f);}
}catch(DomainException $e){$_SESSION['mensaje_sync_general']=['tipo'=>'aviso','texto'=>$e->getMessage()];}
catch(Throwable $e){error_log('Sincronizacion manual: '.get_class($e));$_SESSION['mensaje_sync_general']=['tipo'=>'error','texto'=>'No se pudo preparar el lote. Revisa el registro de errores del sistema.'];}
asesconRedirigir($volverDashboard?'vistas/panel_principal.php':'vistas/utilitarios/sincronizacion.php#syncPanel');
