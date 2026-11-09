<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../servicios/casos_sunat/AccesoCasosSol.php';
asesconExigirLogin();header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
try{
    if(!asesconTienePermiso($conexion,'BUZON_SOL','ver')){http_response_code(403);throw new DomainException('No tienes acceso a los avisos de SUNAT.');}
    $actor=(int)asesconUsuarioActual($conexion)['id_usuario'];$repo=AccesoCasosSol::repositorio();
    if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
        if(!asesconValidarCsrf((string)($_POST['csrf_token']??''),'csrf_avisos_casos')){http_response_code(403);throw new DomainException('Recarga la página antes de actualizar los avisos.');}
        if($repo&&$repo->disponible())$repo->marcarLeida($actor,(int)($_POST['id_notificacion']??0));
    }
    echo json_encode(['ok'=>true,'notificaciones'=>$repo?$repo->notificaciones($actor,asesconEsPrincipal($conexion)):[]],JSON_UNESCAPED_UNICODE);
}catch(DomainException $e){if(http_response_code()<400)http_response_code(422);echo json_encode(['ok'=>false,'mensaje'=>$e->getMessage()]);}
catch(Throwable $e){http_response_code(503);echo json_encode(['ok'=>false,'mensaje'=>'No se pudieron cargar las notificaciones de casos.']);}
