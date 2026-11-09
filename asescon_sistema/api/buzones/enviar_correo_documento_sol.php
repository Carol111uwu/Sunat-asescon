<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../servicios/casos_sunat/AccesoCasosSol.php';
require_once __DIR__.'/../../modelos/buzones/RepositorioDocumentoBuzon.php';
require_once __DIR__.'/../../servicios/archivos/ServicioArchivosPrivados.php';
require_once __DIR__.'/../../servicios/sunat/ServicioCorreoBuzonSol.php';
asesconExigirLogin();header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
try{
    if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||!asesconValidarCsrf((string)($_POST['csrf_token']??''),'csrf_buzon_sol')){http_response_code(403);throw new DomainException('Recarga el formulario de correo.');}
    if(!asesconTienePermiso($conexion,'BUZON_SOL','editar')){http_response_code(403);throw new DomainException('No tienes permiso para enviar correos.');}
    $id=(int)($_POST['id_documento']??0);$doc=(new RepositorioDocumentoBuzon($conexion))->obtener('SOL',$id,AccesoCasosSol::filtro($conexion,asesconFiltroClientesSql($conexion,'c')));
    if(!$doc){http_response_code(404);throw new DomainException('La notificación no está disponible.');}
    $destinatario=trim((string)($_POST['destinatario']??''));$texto=trim((string)($_POST['mensaje']??''));
    if(!filter_var($destinatario,FILTER_VALIDATE_EMAIL)||$texto===''||strlen($texto)>20000)throw new DomainException('Revisa el destinatario y el mensaje.');
    $archivo=!empty($_POST['adjuntar'])?(new ServicioArchivosPrivados())->resolver((string)($doc['archivo']??''),'SOL'):null;
    if(!empty($_POST['adjuntar'])&&!$archivo){http_response_code(409);throw new DomainException('El PDF sigue pendiente. Puedes enviar el resumen desmarcando Adjuntar PDF.');}
    (new ServicioCorreoBuzonSol())->enviar($destinatario,'Notificación SOL — '.$doc['ruc'],$texto,$doc,$archivo);
    echo json_encode(['ok'=>true,'mensaje'=>$archivo?'El servidor de correo aceptó el mensaje con el PDF adjunto.':'El servidor de correo aceptó el mensaje con el resumen.']);
}catch(DomainException $e){if(http_response_code()<400)http_response_code(422);echo json_encode(['ok'=>false,'mensaje'=>$e->getMessage()]);}
catch(Throwable $e){http_response_code(500);error_log('Correo SOL: '.get_class($e));echo json_encode(['ok'=>false,'mensaje'=>'El servidor SMTP no confirmó el envío.']);}
