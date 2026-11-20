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
    $descargar=!empty($_GET['descargar']);
    $liberar=null;
    if ($descargar && getenv('ASESCON_PDF_SOLO_DISPOSITIVO')==='1' && in_array($buzon,['SOL','SUNAFIL'],true)) {
        // El cliente conserva su PDF. En el servidor sólo se mantiene mientras
        // se transmite; se preserva el registro de notificación y sus estados.
        $liberar=static function()use($conexion,$id,$buzon,$ruta):void{
            $tabla=$buzon==='SOL'?'buzon_sol_sunat':'buzon_sunafil';
            $q=$conexion->prepare("UPDATE {$tabla} SET archivo='' WHERE id_documento=?");
            if(!$q)throw new RuntimeException('No se pudo liberar la referencia al PDF.');
            $q->bind_param('i',$id);$q->execute();$q->close();
            @unlink($ruta);
        };
    }
    $archivos->servir($ruta,$descargar,strtolower($buzon).'_'.$id.'.pdf',$liberar);
}catch(DomainException $e){http_response_code(400);exit($e->getMessage());}
