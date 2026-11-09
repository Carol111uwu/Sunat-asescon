<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../modelos/buzones/RepositorioDocumentoBuzon.php';
require_once __DIR__.'/../../modelos/buzones/SeguimientoNotificacionesSol.php';
require_once __DIR__.'/../../servicios/sunat/ServicioColaBuzonSol.php';
require_once __DIR__.'/../../servicios/archivos/ServicioArchivosPrivados.php';
require_once __DIR__.'/../../servicios/sunat/ServicioCorreoBuzonSol.php';
require_once __DIR__.'/../../servicios/casos_sunat/AccesoCasosSol.php';
asesconExigirLogin();
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
function respuestaSeguimiento(int $codigo,bool $ok,string $mensaje,array $datos=[]): never {
    http_response_code($codigo);echo json_encode(['ok'=>$ok,'mensaje'=>$mensaje,'data'=>$datos],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);exit;
}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')respuestaSeguimiento(405,false,'Método no permitido.');
if(!asesconValidarCsrf((string)($_POST['csrf_token']??''),'csrf_buzon_sol'))respuestaSeguimiento(403,false,'Sesión vencida. Actualiza la página.');
try {
    $accion=(string)($_POST['accion']??'');$actor=asesconUsuarioActual($conexion);$idActor=(int)$actor['id_usuario'];
    $repo=new SeguimientoNotificacionesSol($conexion);
    if($accion==='habilitar'){
        if(!asesconEsPrincipal($conexion))respuestaSeguimiento(403,false,'Solo la cuenta principal puede preparar el seguimiento.');
        $repo->habilitar();respuestaSeguimiento(200,true,'Seguimiento habilitado.');
    }
    asesconExigirPermiso($conexion,'BUZON_SOL','ver');
    $id=(int)($_POST['id_documento']??0);
    $doc=(new RepositorioDocumentoBuzon($conexion))->obtener('SOL',$id,AccesoCasosSol::filtro($conexion,asesconFiltroClientesSql($conexion,'c')));
    if(!$doc || (int)$doc['id_cliente']<1)respuestaSeguimiento(404,false,'No se encontró la notificación autorizada.');
    $cliente=(int)$doc['id_cliente'];$caso=$repo->caso($id);
    $esPrincipal=asesconEsPrincipal($conexion);
    $puedeGestionar=$esPrincipal && asesconTienePermiso($conexion,'BUZON_SOL','editar');
    $puedeReportar=$puedeGestionar || ((int)($caso['id_responsable']??0)===$idActor && ($caso['estado']??'ABIERTO')!=='CERRADO');
    if($accion==='pdf'){
        $archivo=(new ServicioArchivosPrivados())->resolver((string)($doc['archivo']??''),'SOL');
        if($archivo)respuestaSeguimiento(200,true,'El PDF ya está disponible.',['disponible'=>true,'url'=>asesconUrl('api/buzones/archivo_documento.php').'?'.http_build_query(['buzon'=>'SOL','id_documento'=>$id,'descargar'=>1])]);
        if(!asesconTienePermiso($conexion,'BUZON_SOL','editar'))respuestaSeguimiento(403,false,'Puedes descargar documentos existentes, pero no solicitar nuevas consultas.');
        $estado=(new ServicioColaBuzonSol())->solicitar($cliente,$idActor,$id);
        respuestaSeguimiento(200,true,'Se solicitó el PDF oficial a SUNAT. La descarga aparecerá al terminar.',['estado'=>$estado['estado']??'PENDIENTE','id_trabajo'=>$estado['id_trabajo']??'']);
    }
    if($accion==='correo'){
        if(!asesconTienePermiso($conexion,'BUZON_SOL','editar'))respuestaSeguimiento(403,false,'No tienes permiso para enviar correos.');
        $destinatario=trim((string)($_POST['destinatario']??''));$mensaje=trim((string)($_POST['mensaje']??''));
        if(!filter_var($destinatario,FILTER_VALIDATE_EMAIL)||$mensaje==='')respuestaSeguimiento(422,false,'Revisa el correo y el mensaje.');
        $ruta=(new ServicioArchivosPrivados())->resolver((string)($doc['archivo']??''),'SOL');
                                                                        
        $adjunto=$ruta && (int)filesize($ruta)<=20*1024*1024?$ruta:null;
        (new ServicioCorreoBuzonSol())->enviar($destinatario,'Notificación SOL - '.(string)($doc['ruc']??''),$mensaje,$doc,$adjunto);
        respuestaSeguimiento(200,true,$ruta&&!$adjunto?'Correo enviado sin el PDF: supera el tamaño aconsejado para adjuntos. Descárgalo desde ASESCON.':($adjunto?'Correo enviado con PDF adjunto.':'Correo enviado con el resumen; no había PDF disponible.'));
    }
    if(!$repo->disponible())respuestaSeguimiento(409,false,'Debes habilitar el seguimiento una vez desde la cuenta principal.');
    if($accion==='asignar'){
        if(!$puedeGestionar)respuestaSeguimiento(403,false,'Solo la cuenta principal puede asignar responsables.');
        $idResponsable=(int)($_POST['id_responsable']??0);
        $repo->guardarAsignacion($id,$cliente,$idResponsable>0?$idResponsable:null,$idActor);
        respuestaSeguimiento(200,true,'Responsable actualizado.');
    }
    if($accion==='nota'){
        if(!$puedeReportar)respuestaSeguimiento(403,false,'Solo el responsable asignado o la cuenta principal pueden registrar avances.');
        $nota=trim((string)($_POST['contenido']??''));
        if($nota==='')respuestaSeguimiento(422,false,'Escribe el avance o la nota.');
                                                                     
        $repo->agregarAvance($id,$cliente,$idActor,$nota);
        respuestaSeguimiento(200,true,'Avance guardado.');
    }
    if($accion==='cerrar'||$accion==='reabrir'){
        if(!$puedeGestionar)respuestaSeguimiento(403,false,'Solo la cuenta principal puede cerrar o reabrir casos.');
        $repo->cambiarEstado($id,$cliente,$idActor,$accion==='cerrar');
        respuestaSeguimiento(200,true,$accion==='cerrar'?'Caso cerrado dentro de ASESCON. No modifica SUNAT.':'Caso reabierto dentro de ASESCON.');
    }
    respuestaSeguimiento(422,false,'Acción no reconocida.');
}catch(DomainException $e){respuestaSeguimiento(422,false,$e->getMessage());}
catch(Throwable $e){error_log('Seguimiento Buzón SOL: '.get_class($e).': '.$e->getMessage());respuestaSeguimiento(500,false,'No fue posible completar la acción. Revisa los permisos y la conexión.');}
