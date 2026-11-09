<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../modelos/buzones/RepositorioDocumentoBuzon.php';
require_once __DIR__.'/../../servicios/casos_sunat/AccesoCasosSol.php';
require_once __DIR__.'/../../servicios/archivos/ServicioArchivosPrivados.php';
require_once __DIR__.'/../../servicios/sunat/ServicioEstadoAdjuntosSol.php';
asesconExigirLogin();header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
try{
    if(!asesconTienePermiso($conexion,'BUZON_SOL','ver')){http_response_code(403);throw new DomainException('No tienes permiso para Buzón SOL.');}
    $id=(int)($_GET['id_documento']??0);$tipo=(string)($_GET['tipo']??'');
    if(!in_array($tipo,['pdf','correo','whatsapp','notas'],true))throw new DomainException('La ventana solicitada no existe.');
    $doc=(new RepositorioDocumentoBuzon($conexion))->obtener('SOL',$id,AccesoCasosSol::filtro($conexion,asesconFiltroClientesSql($conexion,'c')));
    if(!$doc){http_response_code(404);throw new DomainException('La notificación no está disponible para tu cuenta.');}
    $archivo=(new ServicioArchivosPrivados())->resolver((string)($doc['archivo']??''),'SOL');$url=$archivo?asesconUrl('api/buzones/archivo_documento.php').'?'.http_build_query(['buzon'=>'SOL','id_documento'=>$id]):null;
    $datos=['id_documento'=>$id,'asunto'=>$doc['asunto'],'cliente'=>$doc['razon_social'],'ruc'=>$doc['ruc'],'puede_editar'=>asesconTienePermiso($conexion,'BUZON_SOL','editar')];
    if($tipo==='pdf'){require_once __DIR__.'/../../servicios/sunat/ServicioColaBuzonSol.php';$estado=(new ServicioColaBuzonSol())->obtenerEstadoCliente((int)$doc['id_cliente']);if((int)($estado['id_documento']??0)===$id && in_array($estado['estado']??'',['PENDIENTE','PROCESANDO','ERROR'],true))$datos['estado_adjunto']=$estado;$datos['archivo_url']=$url;$datos['estado_adjunto']=$datos['estado_adjunto']??($archivo?['estado'=>'LISTO','mensaje'=>'PDF guardado.']:(new ServicioEstadoAdjuntosSol())->obtener((int)$doc['id_cliente'],$id));}
    if($tipo==='correo'){$datos['destinatario']=$doc['correo_cliente']??'';$datos['tiene_pdf']=$archivo!==null;}
    if($tipo==='whatsapp'){$datos['telefono']=$doc['celular_cliente']??'';$datos['archivo_url']=$url;$datos['resumen']='Cliente: '.$doc['razon_social']."\nRUC: ".$doc['ruc']."\nAsunto: ".$doc['asunto'];}
    if($tipo==='notas'){
        $repo=AccesoCasosSol::repositorio();$datos['notas']=$repo->notas($id);$datos['puede_eliminar']=asesconEsPrincipal($conexion)&&asesconTienePermiso($conexion,'BUZON_SOL','editar');$datos['puede_editar']=AccesoCasosSol::puedeAnotar($conexion,$id);
    }
    echo json_encode(['ok'=>true,'data'=>$datos],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
}catch(DomainException $e){if(http_response_code()<400)http_response_code(422);echo json_encode(['ok'=>false,'mensaje'=>$e->getMessage()]);}
catch(Throwable $e){http_response_code(503);error_log('Ventana SOL: '.get_class($e));echo json_encode(['ok'=>false,'mensaje'=>'No se pudo cargar esta ventana. Revisa la conexión a la base principal de ASESCON.']);}
