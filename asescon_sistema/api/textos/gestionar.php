<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../servicios/textos/CatalogoTextos.php';
asesconExigirLogin();header('Content-Type: application/json; charset=utf-8');header('Cache-Control: private, no-store');
try{
    $metodo=$_SERVER['REQUEST_METHOD']??'';$p=$metodo==='GET'?$_GET:$_POST;$accion=(string)($p['accion']??'listar');$repo=new CatalogoTextos($conexion);$usuario=(int)asesconUsuarioActual($conexion)['id_usuario'];$archivo=new ArchivoTextos($usuario);
    if($metodo==='GET'&&$accion==='estados'){$r=['archivados'=>array_keys($archivo->listar())];}
    elseif($metodo==='GET'&&$accion==='detalle'){$d=$repo->obtener((string)($p['tipo']??''),(int)($p['id']??0));if(!$d){http_response_code(404);throw new DomainException('Texto no disponible.');}$r=['texto'=>$d];}
    elseif($metodo==='GET'&&$accion==='listar'){$r=$repo->listar((string)($p['tipo']??''),max(0,(int)($p['desde']??0)));}
    else{
        if($metodo!=='POST'){http_response_code(405);throw new DomainException('Usa el formulario.');}
        if(!asesconValidarCsrf((string)($p['csrf_token']??''),'csrf_textos')){http_response_code(403);throw new DomainException('Recarga la página antes de continuar.');}
        $tipo=(string)($p['tipo']??'');$id=(int)($p['id']??0);$d=$repo->obtener($tipo,$id);
        if(!$d){http_response_code(404);throw new DomainException('El texto no está disponible para tu cuenta.');}
        if($accion==='eliminar')$repo->eliminar($tipo,$id,(string)($p['revision']??''));
        elseif($accion==='editar')$repo->editar($tipo,$id,(string)($p['revision']??''),(string)($p['texto']??''));
        elseif(in_array($accion,['archivar','restaurar'],true))$archivo->cambiar($tipo,$id,$accion==='archivar');
        else throw new DomainException('Acción no permitida.');
        $r=['mensaje'=>match($accion){'eliminar'=>'Texto eliminado.','editar'=>'Texto actualizado.','archivar'=>'Texto archivado para tu cuenta.',default=>'Texto restaurado.'}];
    }
    echo json_encode(['ok'=>true,'data'=>$r],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(DomainException $e){if(http_response_code()<400)http_response_code(422);echo json_encode(['ok'=>false,'mensaje'=>$e->getMessage()]);}
catch(Throwable $e){http_response_code(503);error_log('Gestión textos: '.get_class($e));echo json_encode(['ok'=>false,'mensaje'=>'No se pudo completar la acción. Se conservó el texto.']);}
