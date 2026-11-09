<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../modelos/seguridad/ContactoEquipo.php';
asesconExigirLogin();header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
try{
    $p=($_SERVER['REQUEST_METHOD']??'GET')==='POST'?$_POST:$_GET;$id=(int)($p['id_usuario']??0);
    if(!asesconPuedeAdministrarUsuario($conexion,$id)){http_response_code(403);throw new DomainException('No puedes modificar este integrante.');}
    $s=$conexion->prepare("SELECT usuario FROM usuarios WHERE id_usuario=? AND tipo_cuenta='EQUIPO'");$s->bind_param('i',$id);$s->execute();$u=$s->get_result()->fetch_assoc();if(!$u)throw new DomainException('El integrante ya no está disponible.');
    $m=new ContactoEquipo($conexion);
    if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
        if(!asesconValidarCsrf((string)($p['csrf_token']??''),'csrf_equipo')){http_response_code(403);throw new DomainException('Recarga el formulario de Mi equipo.');}
        $m->guardar($id,(string)($p['nombre']??''),(string)($p['correo']??''));
    }
    echo json_encode(['ok'=>true,'contacto'=>$m->obtener($id),'usuario'=>$u['usuario']],JSON_UNESCAPED_UNICODE);
}catch(DomainException $e){if(http_response_code()<400)http_response_code(422);echo json_encode(['ok'=>false,'mensaje'=>$e->getMessage()]);}
catch(Throwable $e){http_response_code(500);error_log('Contacto de equipo: '.get_class($e));echo json_encode(['ok'=>false,'mensaje'=>'No se pudo guardar el contacto.']);}
