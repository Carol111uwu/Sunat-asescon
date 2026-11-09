<?php
declare(strict_types=1);
final class ClaveComprobante
{
    public static function normalizar(array $entrada):array
    {
        $id=(int)($entrada['id_cliente']??0);$ruc=preg_replace('/\D/','',(string)($entrada['ruc_emisor']??''));$tipo=str_pad(trim((string)($entrada['tipo_documento']??$entrada['tipo_comprobante']??'')),2,'0',STR_PAD_LEFT);$serie=strtoupper(trim((string)($entrada['serie']??'')));$numero=ltrim(trim((string)($entrada['numero']??$entrada['numero_documento']??'')),'0')?:'0';
        if($id<1||!preg_match('/^\d{11}$/D',$ruc)||!preg_match('/^\d{2}$/D',$tipo)||!preg_match('/^[A-Z0-9-]{1,20}$/D',$serie)||!preg_match('/^\d{1,12}$/D',$numero))throw new DomainException('Revisa cliente, RUC emisor, tipo, serie y número del comprobante.');
        return ['id_cliente'=>$id,'ruc_emisor'=>$ruc,'tipo_documento'=>$tipo,'serie'=>$serie,'numero'=>$numero];
    }
    public static function identificador(array $clave):string{return implode('|',array_values(self::normalizar($clave)));}
}
