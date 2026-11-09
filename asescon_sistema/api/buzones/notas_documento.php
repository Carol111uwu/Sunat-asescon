<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../modelos/buzones/RepositorioDocumentoBuzon.php';
require_once __DIR__.'/../../servicios/casos_sunat/AccesoCasosSol.php';
require_once __DIR__.'/../../servicios/buzones/ServicioNotasBuzon.php';
asesconExigirLogin();header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
try{
    if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);header('Allow: POST');throw new DomainException('La nota debe enviarse desde el formulario.');}
    $buzon=strtoupper((string)($_POST['buzon']??''));$permiso=RepositorioDocumentoBuzon::permiso($buzon);
                                                                                                          
                                                                                 
    if($buzon==='SOL'){
        if(!asesconTienePermiso($conexion,'BUZON_SOL','ver')){http_response_code(403);throw new DomainException('No tienes acceso al Buzón SOL.');}
    }elseif(!asesconTienePermiso($conexion,$permiso,'editar')){http_response_code(403);throw new DomainException('No tienes permiso para añadir notas.');}
    if(!asesconValidarCsrf((string)($_POST['csrf_token']??''),$buzon==='SOL'?'csrf_buzon_sol':'csrf_buzon_sunafil')){http_response_code(403);throw new DomainException('La sesión del formulario venció. Recarga la página.');}
    $id=(int)($_POST['id_documento']??0);$d=(new RepositorioDocumentoBuzon($conexion))->obtener($buzon,$id,($buzon==='SOL'?AccesoCasosSol::filtro($conexion,asesconFiltroClientesSql($conexion,'c')):asesconFiltroClientesSql($conexion,'c')));
    if(!$d||(int)$d['id_cliente']<1){http_response_code(404);throw new DomainException('La notificación no está disponible para tu cuenta.');}
    $actor=asesconUsuarioActual($conexion);
    if($buzon==='SOL'){
        if(!AccesoCasosSol::puedeAnotar($conexion,$id)){http_response_code(403);throw new DomainException('Solo el responsable del caso o la cuenta principal pueden registrar avances.');}
        $texto=trim((string)($_POST['nota']??''));$clave=(string)($_POST['clave_nota']??'');
        if($texto===''||strlen($texto)>20000||preg_match('//u',$texto)!==1||!preg_match('/^[a-zA-Z0-9_-]{16,80}$/D',$clave))throw new DomainException('Revisa el texto de la nota.');
        $notas=(AccesoCasosSol::repositorio())->agregarNota($id,(int)$actor['id_usuario'],(string)$actor['usuario'],$texto,$clave);
    }else{
        $notas=(new ServicioNotasBuzon())->agregar($buzon,(int)$d['id_cliente'],$id,(int)$actor['id_usuario'],(string)$actor['usuario'],(string)($_POST['nota']??''),(string)($_POST['clave_nota']??''));
    }
    echo json_encode(['ok'=>true,'notas'=>$notas],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(DomainException $e){if(http_response_code()<400)http_response_code(400);echo json_encode(['ok'=>false,'mensaje'=>$e->getMessage()]);}
catch(Throwable $e){http_response_code(500);error_log('Notas del buzón: '.get_class($e));echo json_encode(['ok'=>false,'mensaje'=>'No se pudo guardar la nota. Tu texto sigue en el formulario.']);}
