<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../modelos/sunat/ArchivoComprobante.php';
require_once __DIR__.'/../../modelos/sunat/ComprobanteSunat.php';
require_once __DIR__.'/../../servicios/archivos/ServicioArchivosPrivados.php';
asesconExigirLogin();header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
try{
    $k=ClaveComprobante::normalizar($_GET);if(!asesconPuedeVerCliente($conexion,$k['id_cliente'])||!array_filter(['COMPROBANTES_SUNAT','RCE'],static fn(string $m):bool=>asesconTienePermiso($conexion,$m,'ver'))){http_response_code(403);echo json_encode(['ok'=>false,'mensaje'=>'Cliente o módulo no autorizado.']);exit;}
    $repo=new ArchivoComprobante($conexion);$d=$repo->obtener($k);$disponible=$repo->disponible();$urls=[];$serv=new ServicioArchivosPrivados();
    foreach(['PDF'=>'archivo_pdf','XML'=>'archivo_xml','CDR'=>'archivo_cdr'] as $tipo=>$col){$ref=(string)($d[$col]??'');if($ref==='')$ref=(string)((new ComprobanteSunat($conexion))->buscarArchivo($k['id_cliente'],$k['ruc_emisor'],$k['tipo_documento'],$k['serie'],$k['numero'],$tipo)['ruta']??'');$urls[$tipo]=$serv->resolver($ref,'COMPROBANTES')?asesconUrl('api/sunat/archivo_comprobante.php').'?'.http_build_query($k+['tipo_archivo'=>$tipo]):null;}
    echo json_encode(['ok'=>true,'data'=>['clave'=>$k,'archivos'=>$urls,'cuenta_contable'=>$d['cuenta_contable']??'','centro_costos'=>$d['centro_costos']??'','cuentas'=>$repo->cuentas($k['id_cliente']),'disponible'=>$disponible]],JSON_UNESCAPED_UNICODE);
}catch(DomainException $e){http_response_code(400);echo json_encode(['ok'=>false,'mensaje'=>$e->getMessage()]);}
catch(Throwable $e){error_log('Detalle de comprobante: '.get_class($e));http_response_code(500);echo json_encode(['ok'=>false,'mensaje'=>'No se pudo cargar el archivo del comprobante.']);}
