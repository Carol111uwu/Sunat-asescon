<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../servicios/casos_sunat/AccesoCasosSol.php';
require_once __DIR__.'/../../modelos/buzones/RepositorioDocumentoBuzon.php';
asesconExigirLogin();header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
try{
    if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);throw new DomainException('Usa el botón de eliminar de la nota.');}
    if(!asesconEsPrincipal($conexion)||!asesconTienePermiso($conexion,'BUZON_SOL','editar')){http_response_code(403);throw new DomainException('Solo la cuenta creadora puede eliminar notas.');}
    if(!asesconValidarCsrf((string)($_POST['csrf_token']??''),'csrf_buzon_sol')){http_response_code(403);throw new DomainException('Recarga el formulario de notas.');}
    $id=(int)($_POST['id_documento']??0);$nota=(int)($_POST['id_nota']??0);
    $doc=(new RepositorioDocumentoBuzon($conexion))->obtener('SOL',$id,AccesoCasosSol::filtro($conexion,asesconFiltroClientesSql($conexion,'c')));
    if(!$doc||$nota<1){http_response_code(404);throw new DomainException('La nota no está disponible para esta notificación.');}
    $notas=AccesoCasosSol::repositorio()->eliminarNota($id,$nota,asesconUsuarioActual($conexion));
    echo json_encode(['ok'=>true,'notas'=>$notas],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(DomainException $e){if(http_response_code()<400)http_response_code(422);echo json_encode(['ok'=>false,'mensaje'=>$e->getMessage()]);}
catch(Throwable $e){http_response_code(503);echo json_encode(['ok'=>false,'mensaje'=>'No se pudo eliminar la nota.']);}
