<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../modelos/buzones/RepositorioDocumentoBuzon.php';
require_once __DIR__.'/../../servicios/casos_sunat/AccesoCasosSol.php';
require_once __DIR__.'/../../servicios/casos_sunat/EquipoAsignableSol.php';
require_once __DIR__.'/../../servicios/casos_sunat/ValidacionAsignacionSol.php';
asesconExigirLogin();header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
try{
    if(!asesconTienePermiso($conexion,'BUZON_SOL','ver')){http_response_code(403);throw new DomainException('No tienes permiso para Buzón SOL.');}
    $post=($_SERVER['REQUEST_METHOD']??'GET')==='POST';$p=$post?$_POST:$_GET;$id=(int)($p['id_documento']??0);
    $doc=(new RepositorioDocumentoBuzon($conexion))->obtener('SOL',$id,AccesoCasosSol::filtro($conexion,asesconFiltroClientesSql($conexion,'c')));
    if(!$doc){http_response_code(404);throw new DomainException('El caso no está disponible para tu cuenta.');}
    $principal=asesconEsPrincipal($conexion);$repo=AccesoCasosSol::repositorio();$equipo=$principal?(new EquipoAsignableSol($conexion))->listar():[];
    $correoProgramado=false;
    if($post){
        if(!$principal||!asesconTienePermiso($conexion,'BUZON_SOL','editar')){http_response_code(403);throw new DomainException('Solo la cuenta principal puede asignar y compartir casos.');}
        if(!asesconValidarCsrf((string)($p['csrf_token']??''),'csrf_buzon_sol')){http_response_code(403);throw new DomainException('Recarga el formulario antes de asignar.');}
        $validado=ValidacionAsignacionSol::preparar($p,$equipo);
        $correoProgramado=(bool)filter_var((string)($validado['responsable']['correo']??''),FILTER_VALIDATE_EMAIL);
        $config=asesconCargarConfiguracionPrivada();$base=rtrim((string)(getenv('ASESCON_URL_PUBLICA')?:($config['url_publica']??'https://sistema.asescon.net')),'/');
        if(!filter_var($base,FILTER_VALIDATE_URL)||!str_starts_with($base,'https://'))throw new DomainException('Configura la URL pública HTTPS de ASESCON.');
        $enlace=$base.asesconUrl('vistas/buzones/mis_casos_sol.php').'?caso='.$id;
        $repo->asignar($doc,$validado['responsable'],$validado['compartidos'],$validado['parametros'],asesconUsuarioActual($conexion),$enlace);
    }
    $mensajeOk=$correoProgramado?'Asignación guardada. Aviso interno creado en ASESCON y correo programado.':'Asignación guardada. Aviso interno creado en ASESCON. El correo es opcional; registra una dirección válida en Mi equipo si también deseas avisar por email.';
    echo json_encode(['ok'=>true,'mensaje'=>$post?$mensajeOk:'','documento'=>['cliente'=>$doc['razon_social'],'asunto'=>$doc['asunto'],'ruc'=>$doc['ruc']],'caso'=>$repo->caso($id),'compartidos'=>$repo->accesos($id),'equipo'=>$equipo,'puede_asignar'=>$principal,'historial'=>$repo->historial($id),'correo_aviso'=>$repo->estadoCorreo($id)],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
}catch(DomainException $e){if(http_response_code()<400)http_response_code(422);echo json_encode(['ok'=>false,'mensaje'=>$e->getMessage()]);}
catch(Throwable $e){http_response_code(503);error_log('Asignación SOL: '.get_class($e));echo json_encode(['ok'=>false,'mensaje'=>'No se pudo acceder a la base principal de ASESCON o guardar el caso. Los cambios no se confirmaron.']);}
