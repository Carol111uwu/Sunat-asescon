<?php
declare(strict_types=1);require_once __DIR__.'/CalculoLiquidacion.php';
final class CalculoPagoTributario
{
 public static function resumen(float|int|string $declarado,float|int|string $pagado):array{
  $total=CalculoLiquidacion::centimos($declarado);$pago=CalculoLiquidacion::centimos($pagado);if($total<0||$pago<0)throw new DomainException('Los importes declarados y pagados no pueden ser negativos.');
  return ['declarado'=>$total/100,'pagado'=>$pago/100,'saldo'=>max(0,$total-$pago)/100,'estado'=>$pago>0?($pago>=$total?'PAGADO':'PARCIAL'):'PENDIENTE'];
 }
 public static function validarNuevoPago(float|int|string $declarado,float|int|string $pagado,float|int|string $nuevo):float{
  $r=self::resumen($declarado,$pagado);$n=CalculoLiquidacion::centimos($nuevo);if($n<=0)throw new DomainException('El pago debe ser mayor que cero.');if($n>CalculoLiquidacion::centimos($r['saldo']))throw new DomainException('El pago supera el saldo registrado de la declaración. Revisa el importe declarado y los pagos anteriores.');return $n/100;
 }
}
