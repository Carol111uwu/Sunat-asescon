<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../servicios/casos_sunat/AccesoCasosSol.php';
require_once __DIR__.'/../../modelos/buzones/RepositorioDocumentoBuzon.php';
asesconExigirLogin();header('Content-Type: application/json; charset=utf-8');
try{
    if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||!asesconValidarCsrf((string)($_POST['csrf_token']??''),'csrf_buzon_sol')){http_response_code(403);throw new DomainException('Recarga Mis casos antes de actualizar.');}
    if(!asesconTienePermiso($conexion,'BUZON_SOL','editar')){http_response_code(403);throw new DomainException('No tienes permiso para actualizar casos.');}
    $id=(int)($_POST['id_documento']??0);$repo=AccesoCasosSol::repositorio();$c=$repo->caso($id);$actor=asesconUsuarioActual($conexion);
    $doc=(new RepositorioDocumentoBuzon($conexion))->obtener('SOL',$id,AccesoCasosSol::filtro($conexion,asesconFiltroClientesSql($conexion,'c')));
    if(!$doc||!$c||(!asesconEsPrincipal($conexion)&&(int)$c['id_responsable']!==(int)$actor['id_usuario'])){http_response_code(403);throw new DomainException('Solo el responsable o la cuenta principal pueden actualizar este caso.');}
    $estado=(string)($_POST['estado']??'');$repo->cambiarEstado($id,$estado,$actor);echo json_encode(['ok'=>true,'mensaje'=>'Estado del caso actualizado.','estado'=>$estado]);
}catch(DomainException $e){if(http_response_code()<400)http_response_code(422);echo json_encode(['ok'=>false,'mensaje'=>$e->getMessage()]);}
catch(Throwable $e){http_response_code(503);echo json_encode(['ok'=>false,'mensaje'=>'No se pudo guardar el estado del caso.']);}
