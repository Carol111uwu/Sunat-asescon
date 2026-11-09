<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../modelos/sunat/ArchivoComprobante.php';
require_once __DIR__.'/../../modelos/sunat/ComprobanteSunat.php';
require_once __DIR__.'/../../servicios/archivos/ServicioArchivosPrivados.php';
asesconExigirLogin();
try{
    $k=ClaveComprobante::normalizar($_GET);if(!asesconPuedeVerCliente($conexion,$k['id_cliente'])||!array_filter(['COMPROBANTES_SUNAT','RCE','RVIE'],static fn(string $m):bool=>asesconTienePermiso($conexion,$m,'ver'))){http_response_code(403);exit('Comprobante no autorizado.');}
    $tipo=strtoupper((string)($_GET['tipo_archivo']??'PDF'));$col=match($tipo){'PDF'=>'archivo_pdf','XML'=>'archivo_xml','CDR'=>'archivo_cdr',default=>throw new DomainException('Tipo no válido.')};
    $fila=(new ArchivoComprobante($conexion))->obtener($k);$ref=(string)($fila[$col]??'');
    if($ref==='')$ref=(string)((new ComprobanteSunat($conexion))->buscarArchivo($k['id_cliente'],$k['ruc_emisor'],$k['tipo_documento'],$k['serie'],$k['numero'],$tipo)['ruta']??'');
    $servicio=new ServicioArchivosPrivados();$ruta=$servicio->resolver($ref,'COMPROBANTES');if(!$ruta){http_response_code(404);exit('No hay un archivo vinculado de este tipo.');}
    $servicio->servir($ruta,$tipo!=='PDF'||!empty($_GET['descargar']),'comprobante_'.$k['serie'].'_'.$k['numero'].'.'.($tipo==='CDR'?'zip':strtolower($tipo)));
}catch(DomainException $e){http_response_code(400);exit($e->getMessage());}
