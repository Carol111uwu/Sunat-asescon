<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';require_once __DIR__.'/../../modelos/sunat/DeclaracionSunat.php';require_once __DIR__.'/../../modelos/sunat/PagoTributario.php';require_once __DIR__.'/../../servicios/archivos/ServicioArchivosPrivados.php';
final class ControladorPagoTributario
{
 public function __construct(private mysqli $db){}
 public function procesar(array $p,array $archivos):void{
  asesconExigirPermiso($this->db,'DECLARACIONES','editar');if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||!asesconValidarCsrf((string)($p['csrf_token']??''),'csrf_pagos')){http_response_code(403);exit('Solicitud no válida.');}
  $id=(int)($p['id_declaracion']??0);$d=null;$transaccion=false;
  try{
   $d=(new DeclaracionSunat($this->db))->obtener($id,asesconFiltroClientesSql($this->db,'c'));if(!$d||!asesconPuedeGestionarCliente($this->db,(int)$d['id_cliente']))throw new DomainException('Declaración no autorizada.');
   $repo=new PagoTributario($this->db);if(!$repo->disponible())throw new DomainException('La cuenta principal debe preparar o actualizar los módulos desde Configuración de correo.');
   $this->db->begin_transaction();$transaccion=true;$actual=$repo->bloquearDeclaracion($id);if(!$actual||!asesconPuedeGestionarCliente($this->db,(int)$actual['id_cliente']))throw new DomainException('La declaración ya no está autorizada.');$pagos=$repo->listar($id);
                                                                                  
   if(!$pagos&&(float)$actual['monto_pagado']>0)$repo->registrar(['id_declaracion'=>$id,'id_cliente'=>(int)$d['id_cliente'],'referencia'=>'SALDO-PREVIO-'.$id,'fecha_pago'=>date('Y-m-d'),'monto'=>(float)$actual['monto_pagado'],'medio'=>'Importe anterior de ASESCON','codigo_nps'=>'','archivo_constancia'=>'','origen'=>'SALDO_PREVIO','registrado_por'=>(int)$_SESSION['id_usuario']]);
   if(($p['accion']??'')==='anular'){
    $motivo=trim((string)($p['motivo']??''));if(preg_match_all('/./us',$motivo)<5||preg_match_all('/./us',$motivo)>500)throw new DomainException('Escribe el motivo de la anulación (5 a 500 caracteres).');$repo->anular($id,(int)($p['id_pago']??0),$motivo);
   }elseif(($p['accion']??'')==='incorporar_anterior'){
    if($pagos||(float)$actual['monto_pagado']<=0)throw new DomainException('El historial ya está iniciado o no existe un importe anterior que incorporar.');
   }elseif(($p['accion']??'')==='registrar'){
    if(empty($p['confirmar_constancia']))throw new DomainException('Confirma que revisaste el pago y su constancia oficial.');
    $ref=strtoupper(trim((string)($p['referencia']??'')));$medio=trim((string)($p['medio']??''));$fecha=(string)($p['fecha_pago']??'');$nps=trim((string)($p['codigo_nps']??''));
    if(!preg_match('/^[A-Z0-9][A-Z0-9 ._\/-]{1,99}$/D',$ref)||$medio===''||preg_match_all('/./us',$medio)>80)throw new DomainException('Revisa referencia y medio de pago.');
    if($nps!==''&&!preg_match('/^[0-9]{1,30}$/D',$nps))throw new DomainException('El NPS debe contener solo dígitos y ser el emitido por SUNAT.');
    $f=DateTimeImmutable::createFromFormat('!Y-m-d',$fecha);if(!$f||$f->format('Y-m-d')!==$fecha||$fecha>date('Y-m-d'))throw new DomainException('La fecha del pago no es válida.');
    if(in_array($ref,array_column(array_filter($repo->listar($id),fn($p)=>$p['estado']==='REGISTRADO'),'referencia'),true))throw new DomainException('Esta referencia ya está registrada para la declaración. No se ha duplicado el pago.');
    $token=(string)($p['idempotencia']??'');if(!preg_match('/^[a-f0-9]{32}$/D',$token))throw new DomainException('Recarga el historial antes de registrar el pago.');
    $monto=CalculoPagoTributario::validarNuevoPago((string)$actual['monto_declarado'],(string)$actual['monto_pagado'],(string)($p['monto']??''));$ruta='';
    if(($archivos['constancia']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)$ruta=(new ServicioArchivosPrivados())->guardarSubida($archivos['constancia'],'PAGOS',(int)$d['id_cliente'],$id);
    $repo->registrar(['id_declaracion'=>$id,'id_cliente'=>(int)$d['id_cliente'],'referencia'=>$ref,'fecha_pago'=>$fecha,'monto'=>$monto,'medio'=>$medio,'codigo_nps'=>$nps,'archivo_constancia'=>$ruta,'origen'=>'REGISTRO_MANUAL','idempotencia'=>hash('sha256',$token),'registrado_por'=>(int)$_SESSION['id_usuario']]);
   }else throw new DomainException('Acción no válida.');
   $repo->conciliar($id);$this->db->commit();$transaccion=false;$_SESSION['mensaje_pago']='Historial actualizado. Este registro no realiza ni revierte un pago bancario.';
  }catch(Throwable $e){if($transaccion)$this->db->rollback();error_log('Pago tributario: '.get_class($e));$_SESSION['error_pago']=$e instanceof DomainException?$e->getMessage():'No se pudo guardar el registro de pago.';}
  asesconRedirigir('vistas/sunat/pagos_tributarios.php',['periodo'=>(string)($d['periodo']??date('Ym')),'ver_declaracion'=>$id]);
 }
}
if(realpath((string)($_SERVER['SCRIPT_FILENAME']??''))===__FILE__)(new ControladorPagoTributario($conexion))->procesar($_POST,$_FILES);
