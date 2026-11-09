<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../modelos/buzones/RepositorioDocumentoBuzon.php';
require_once __DIR__.'/../../modelos/buzones/SeguimientoNotificacionesSol.php';
require_once __DIR__.'/../../servicios/archivos/ServicioArchivosPrivados.php';
require_once __DIR__.'/../../servicios/casos_sunat/AccesoCasosSol.php';
asesconExigirLogin();header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
try{
    $buzon=strtoupper((string)($_GET['buzon']??''));$permiso=RepositorioDocumentoBuzon::permiso($buzon);
    if(!asesconTienePermiso($conexion,$permiso,'ver')){http_response_code(403);echo json_encode(['ok'=>false,'mensaje'=>'No tienes permiso para este buzón.']);exit;}
    $id=(int)($_GET['id_documento']??0);$d=(new RepositorioDocumentoBuzon($conexion))->obtener($buzon,$id,($buzon==='SOL'?AccesoCasosSol::filtro($conexion,asesconFiltroClientesSql($conexion,'c')):asesconFiltroClientesSql($conexion,'c')));
    if(!$d){http_response_code(404);echo json_encode(['ok'=>false,'mensaje'=>'La notificación no está disponible para tu cuenta.']);exit;}
    $ruta=(new ServicioArchivosPrivados())->resolver((string)($d['archivo']??''),$buzon);
    $url=asesconUrl('api/buzones/archivo_documento.php').'?'.http_build_query(['buzon'=>$buzon,'id_documento'=>$id]);
    $solicitudPdf=null;
    $solicitudDetalle=null;
    $detalleOficial=null;
    if($buzon==='SOL'&&(int)($d['id_cliente']??0)>0){
        require_once __DIR__.'/../../servicios/sunat/ServicioColaBuzonSol.php';
        $estadoPdf=(new ServicioColaBuzonSol())->obtenerEstadoCliente((int)$d['id_cliente']);
        if((int)($estadoPdf['id_documento']??0)===$id){
            $estadoActual=['estado'=>$estadoPdf['estado']??'','mensaje'=>$estadoPdf['mensaje']??''];
            if(($estadoPdf['modo_documento']??'PDF')==='DETALLE')$solicitudDetalle=$estadoActual;
            else $solicitudPdf=$estadoActual+['bytes_descargados'=>$estadoPdf['bytes_descargados']??0,'bytes_totales'=>$estadoPdf['bytes_totales']??0];
        }
        require_once __DIR__.'/../../servicios/sunat/RepositorioDetalleOficialSol.php';
        $detalleOficial=(new RepositorioDetalleOficialSol($conexion))->obtener($id);
    }
    $seguimiento=['habilitado'=>false,'caso'=>null,'avances'=>[],'responsables'=>[],'puede_asignar'=>false,'puede_reportar'=>false,'puede_habilitar'=>asesconEsPrincipal($conexion)];
    if($buzon==='SOL'){
        $seg=new SeguimientoNotificacionesSol($conexion);
        $seguimiento['habilitado']=$seg->disponible();
        if($seguimiento['habilitado']){
            $caso=$seg->caso($id);$actor=asesconUsuarioActual($conexion);
            $gestion=asesconEsPrincipal($conexion)&&asesconTienePermiso($conexion,'BUZON_SOL','editar');
            $seguimiento['caso']=$caso;
            $seguimiento['avances']=$seg->avances($id);
            $seguimiento['puede_asignar']=$gestion;
            $seguimiento['puede_reportar']=$gestion || ((int)($caso['id_responsable']??0)===(int)$actor['id_usuario'] && ($caso['estado']??'ABIERTO')!=='CERRADO');
            if($gestion)$seguimiento['responsables']=$seg->responsables((int)$d['id_cliente']);
        }
    }
    require_once __DIR__.'/../../servicios/buzones/ServicioNotasBuzon.php';
    $notas=$buzon==='SOL'?AccesoCasosSol::repositorio()->notas($id):(new ServicioNotasBuzon())->listar($buzon,(int)$d['id_cliente'],$id);
    echo json_encode(['ok'=>true,'data'=>['notas'=>$notas,'id_documento'=>$id,'cliente'=>$d['razon_social']??'Sin cliente','ruc'=>$d['ruc']??'','asunto'=>$d['asunto'],'fecha'=>$d['fecha_recepcion']?:$d['fecha_documento'],'tipo'=>$d['tipo_documento']??'','categoria'=>$d['categoria']??'','estado'=>$d['estado'],'contenido'=>$d['observaciones']??'','contenido_oficial'=>$detalleOficial,'solicitud_detalle'=>$solicitudDetalle,'correo'=>$d['correo_cliente']??'','celular'=>$d['celular_cliente']??'','solicitud_pdf'=>$solicitudPdf,'seguimiento'=>$seguimiento,'tiene_cliente'=>(int)($d['id_cliente']??0)>0,'archivo_disponible'=>$ruta!==null,'archivo_url'=>$ruta?$url:null,'puede_editar'=>asesconTienePermiso($conexion,$permiso,'editar')]],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
}catch(DomainException $e){http_response_code(400);echo json_encode(['ok'=>false,'mensaje'=>$e->getMessage()]);}
catch(Throwable $e){error_log('Detalle de buzón: '.get_class($e));http_response_code(500);echo json_encode(['ok'=>false,'mensaje'=>'No se pudo cargar la notificación.']);}
