<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
date_default_timezone_set('America/Lima');
require_once __DIR__.'/../../configuracion/conexion.php';
require_once __DIR__.'/../../servicios/casos_sunat/AccesoCasosSol.php';
require_once __DIR__.'/../../servicios/casos_sunat/EquipoAsignableSol.php';
require_once __DIR__.'/../../servicios/comunicaciones/ServicioCorreoSmtp.php';
try{
    $db=asesconConexionPrincipal();$repo=AccesoCasosSol::repositorio();if(!$repo->disponible())exit;
    $db->query("UPDATE sunat_casos_correos SET estado='PENDIENTE' WHERE estado='ENVIANDO' AND actualizado_en<DATE_SUB(NOW(),INTERVAL 5 MINUTE)");
    $equipo=[];foreach((new EquipoAsignableSol($conexion))->listar() as $u)$equipo[(int)$u['id_usuario']]=$u;
    for($i=0;$i<3;$i++){
        $db->begin_transaction();$fila=$db->query("SELECT * FROM sunat_casos_correos WHERE estado IN('PENDIENTE','ERROR') AND siguiente_intento<=NOW() AND intentos<5 ORDER BY id_correo LIMIT 1 FOR UPDATE")->fetch_assoc();
        if(!$fila){$db->commit();break;}
        $id=(int)$fila['id_correo'];$db->query("UPDATE sunat_casos_correos SET estado='ENVIANDO',intentos=intentos+1 WHERE id_correo={$id}");$db->commit();
        $uid=(int)$fila['id_usuario'];$caso=(int)$fila['id_documento'];$estado='ENVIADO';$error='';
        if(!isset($equipo[$uid])||!in_array($caso,$repo->ids($uid),true)){$estado='CANCELADO';$error='El destinatario ya no tiene acceso al caso.';}
        else try{(new ServicioCorreoSmtp())->enviar($equipo[$uid]['correo'],(string)$fila['asunto'],(string)$fila['mensaje'],'ASESCON');}
        catch(Throwable $e){$estado='ERROR';$error='El servidor SMTP no confirmó el envío.';error_log('Correo de asignación SOL: '.get_class($e));}
        $s=$db->prepare('UPDATE sunat_casos_correos SET estado=?,ultimo_error=?,siguiente_intento=DATE_ADD(NOW(),INTERVAL 5 MINUTE) WHERE id_correo=?');$s->bind_param('ssi',$estado,$error,$id);$s->execute();
    }
}catch(Throwable $e){error_log('Cola de asignaciones SOL: '.get_class($e));exit(1);}
