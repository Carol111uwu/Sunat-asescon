<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../modelos/buzones/RepositorioDocumentoBuzon.php';
require_once __DIR__.'/../../servicios/archivos/ServicioArchivosPrivados.php';
require_once __DIR__.'/../../servicios/casos_sunat/AccesoCasosSol.php';
asesconExigirLogin();
try{
    $buzon=strtoupper((string)($_GET['buzon']??''));$permiso=RepositorioDocumentoBuzon::permiso($buzon);asesconExigirPermiso($conexion,$permiso,'ver');
    $id=(int)($_GET['id_documento']??0);$d=(new RepositorioDocumentoBuzon($conexion))->obtener($buzon,$id,($buzon==='SOL'?AccesoCasosSol::filtro($conexion,asesconFiltroClientesSql($conexion,'c')):asesconFiltroClientesSql($conexion,'c')));
    if(!$d){http_response_code(404);exit('Documento no disponible para tu cuenta.');}
    $archivos=new ServicioArchivosPrivados();$ruta=$archivos->resolver((string)($d['archivo']??''),$buzon);
    if(!$ruta){http_response_code(404);exit('No hay un archivo PDF vinculado a esta notificación.');}
    $archivos->servir($ruta,!empty($_GET['descargar']),strtolower($buzon).'_'.$id.'.pdf');
}catch(DomainException $e){http_response_code(400);exit($e->getMessage());}
