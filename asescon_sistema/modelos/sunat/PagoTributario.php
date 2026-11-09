<?php
declare(strict_types=1);require_once __DIR__.'/../../servicios/sunat/CalculoPagoTributario.php';
final class PagoTributario
{
 public function __construct(private mysqli $conexion){}
 public function disponible():bool{return (int)($this->conexion->query("SELECT COUNT(*) n FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='pagos_tributarios'")->fetch_assoc()['n']??0)===1;}
 public function listar(int $id):array{
  if(!$this->disponible())return [];$s=$this->conexion->prepare('SELECT id_pago,referencia,fecha_pago,monto,medio,codigo_nps,archivo_constancia,origen,estado,motivo_anulacion FROM pagos_tributarios WHERE id_declaracion=? ORDER BY fecha_pago,id_pago');$s->bind_param('i',$id);$s->execute();$r=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();return $r;
 }
 public function bloquearDeclaracion(int $id):?array{$s=$this->conexion->prepare('SELECT * FROM declaraciones_sunat WHERE id_declaraciones_sunat=? FOR UPDATE');$s->bind_param('i',$id);$s->execute();$r=$s->get_result()->fetch_assoc();$s->close();return $r;}
 public function registrar(array $d):void{
  $idempotencia=$d['idempotencia']??hash('sha256','APERTURA|'.$d['id_declaracion']);
  $s=$this->conexion->prepare("INSERT INTO pagos_tributarios(id_declaracion,id_cliente,referencia,fecha_pago,monto,medio,codigo_nps,archivo_constancia,origen,registrado_por,idempotencia) VALUES(?,?,?,?,?,?,NULLIF(?,''),NULLIF(?,''),?,?,?)");
  $s->bind_param('iissdssssis',$d['id_declaracion'],$d['id_cliente'],$d['referencia'],$d['fecha_pago'],$d['monto'],$d['medio'],$d['codigo_nps'],$d['archivo_constancia'],$d['origen'],$d['registrado_por'],$idempotencia);$s->execute();$s->close();
 }
 public function conciliar(int $id):array{
  $s=$this->conexion->prepare("SELECT COALESCE(SUM(CASE WHEN estado='REGISTRADO' THEN monto ELSE 0 END),0) total FROM pagos_tributarios WHERE id_declaracion=?");$s->bind_param('i',$id);$s->execute();$total=(float)$s->get_result()->fetch_assoc()['total'];$s->close();
  $d=$this->bloquearDeclaracion($id);$r=CalculoPagoTributario::resumen((string)$d['monto_declarado'],$total);$estado=$r['estado'];$s=$this->conexion->prepare("UPDATE declaraciones_sunat SET monto_pagado=?,estado_pago=? WHERE id_declaraciones_sunat=?");$s->bind_param('dsi',$total,$estado,$id);$s->execute();$s->close();return $r;
 }
 public function anular(int $id,int $pago,string $motivo):void{
  $s=$this->conexion->prepare("UPDATE pagos_tributarios SET estado='ANULADO',motivo_anulacion=? WHERE id_pago=? AND id_declaracion=? AND estado='REGISTRADO'");$s->bind_param('sii',$motivo,$pago,$id);$s->execute();$cambios=$s->affected_rows;$s->close();if($cambios!==1)throw new DomainException('El pago no existe o ya fue anulado. Actualiza el historial.');
 }
}
