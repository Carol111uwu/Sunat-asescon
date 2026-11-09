<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../modelos/sunat/DeclaracionSunat.php';
require_once __DIR__.'/../../servicios/archivos/ServicioArchivosPrivados.php';
require_once __DIR__.'/../../servicios/sunat/CalculoLiquidacion.php';
asesconExigirPermiso($conexion,'DECLARACIONES','editar');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||!asesconValidarCsrf((string)($_POST['csrf_token']??''),'csrf_declaraciones')){http_response_code(403);exit('Solicitud no válida.');}
try{
    $repo=new DeclaracionSunat($conexion);$id=(int)($_POST['id']??0);$d=$repo->obtener($id,asesconFiltroClientesSql($conexion,'c'));
    if(!$d||!asesconPuedeGestionarCliente($conexion,(int)$d['id_cliente']))throw new DomainException('La declaración no está autorizada.');
    $orden=trim((string)($_POST['numero_orden']??''));if(!preg_match('/^[A-Za-z0-9-]{0,50}$/D',$orden))throw new DomainException('Número de orden no válido.');
    $estado=(string)($_POST['estado_presentacion']??'');if(!in_array($estado,['PENDIENTE','PRESENTADA'],true))throw new DomainException('Estado no válido.');
    $fecha=trim((string)($_POST['fecha_presentacion']??''));
    if($fecha!==''){$formato=str_contains($fecha,'T')?'Y-m-d\TH:i':'Y-m-d H:i:s';$f=DateTimeImmutable::createFromFormat('!'.$formato,$fecha);if(!$f||$f->format($formato)!==$fecha)throw new DomainException('Fecha de presentación no válida.');$fecha=$f->format('Y-m-d H:i:s');}
    $monto=CalculoLiquidacion::centimos((string)($_POST['monto_declarado']??'0'))/100;if($monto<0)throw new DomainException('El monto declarado debe ser no negativo.');
    if($estado==='PRESENTADA'&&($orden===''||$fecha===''||empty($_POST['confirmar_constancia'])))throw new DomainException('Para marcar como presentada, registra orden, fecha y confirma que revisaste la constancia oficial.');
    $ruta='';if(($_FILES['constancia']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)$ruta=(new ServicioArchivosPrivados())->guardarSubida($_FILES['constancia'],'DECLARACIONES',(int)$d['id_cliente'],$id);
    $repo->guardarConstancia($id,$ruta,$orden,$fecha,$monto,$estado);$_SESSION['mensaje_constancia']='Constancia y datos guardados como registro manual. No se realizó una consulta a SUNAT.';
}catch(Throwable $e){error_log('Constancia: '.get_class($e));$_SESSION['error_constancia']=$e instanceof DomainException?$e->getMessage():'No se pudo guardar la constancia.';}
asesconRedirigir('vistas/sunat/control_declaraciones.php',['periodo'=>(string)($d['periodo']??date('Ym'))]);
