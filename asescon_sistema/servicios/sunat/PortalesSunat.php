<?php
declare(strict_types=1);
final class PortalesSunat
{
 public const DESTINOS=[
  'declaraciones'=>['nombre'=>'Mis declaraciones y pagos','url'=>'https://e-menu.sunat.gob.pe/cl-ti-itmenu2/MenuInternetPlataforma.htm?exe=55.1.1.1.1'],
  'tramites'=>['nombre'=>'Trámites y consultas SOL','url'=>'https://e-menu.sunat.gob.pe/cl-ti-itmenucabina/MenuInternet.htm'],
  'buzon'=>['nombre'=>'Buzón SOL','url'=>'https://e-menu.sunat.gob.pe/cl-ti-itmenucabina/MenuInternet.htm?exe=buzon'],
 ];
 public static function destino(string $codigo):array{return self::DESTINOS[$codigo]??throw new DomainException('Destino SUNAT no válido.');}
 public static function puedeAcceder(mysqli $db,int $cliente):bool{
  if(!asesconPuedeGestionarCliente($db,$cliente))return false;
  foreach(['CLIENTES'=>'editar','DECLARACIONES'=>'ejecutar','PDT_0621'=>'ejecutar','SIRE'=>'ejecutar','BUZON_SOL'=>'editar'] as $m=>$a)if(asesconTienePermiso($db,$m,$a))return true;
  return false;
 }
}
