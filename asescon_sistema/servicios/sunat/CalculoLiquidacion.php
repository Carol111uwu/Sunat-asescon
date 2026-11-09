<?php
declare(strict_types=1);

final class CalculoLiquidacion
{
    public static function centimos(float|int|string|null $valor):int
    {
        $texto=trim((string)($valor??'0'));
        if(!preg_match('/^-?\d{1,9}(?:\.\d{1,4})?$/D',$texto))throw new DomainException('Escribe importes numéricos con punto decimal, sin separadores de miles.');
        return (int)round((float)$texto*100,0,PHP_ROUND_HALF_UP);
    }
    public static function calcular(array $datos):array
    {
        $campos=['igv_ventas','credito_compras','credito_fiscal_anterior','percepciones','retenciones','renta','saldo_anterior','essalud','onp','renta_quinta','itan','otros'];$c=[];
        foreach($campos as $campo)$c[$campo]=self::centimos($datos[$campo]??0);
        foreach(['credito_fiscal_anterior','percepciones','retenciones','renta','saldo_anterior','essalud','onp','renta_quinta','itan','otros'] as $campo)if($c[$campo]<0)throw new DomainException('Los saldos aplicables y otros tributos deben ser importes no negativos.');
        $resultado=$c['igv_ventas']-$c['credito_compras'];
        $igv=$resultado-$c['credito_fiscal_anterior']-$c['percepciones']-$c['retenciones'];
        $renta=max(0,$c['renta']-$c['saldo_anterior']);
        $total=max(0,$igv)+$renta+$c['essalud']+$c['onp']+$c['renta_quinta']+$c['itan']+$c['otros'];
        $salida=[];foreach($c as $campo=>$valor)$salida[$campo]=$valor/100;
        return array_merge($salida,['impuesto_resultado'=>$resultado/100,'igv_pagar_sf'=>$igv/100,'igv'=>max(0,$igv)/100,'renta_pagar'=>$renta/100,'total_pagar'=>$total/100]);
    }
    public static function totalesSire(array $filas):array
    {
        $c=['base'=>0,'igv'=>0,'total'=>0];$grupos=[];
        foreach($filas as $f){
            $tipo=(string)($f['tipo_comprobante']??'—');$grupos[$tipo]??=['tipo'=>$tipo,'documentos'=>0,'base'=>0,'igv'=>0,'total'=>0];$grupos[$tipo]['documentos']++;
            foreach(['base'=>'base_imponible','igv'=>'igv','total'=>'importe_total'] as $nombre=>$campo){$m=self::centimos($f[$campo]??0);$c[$nombre]+=$m;$grupos[$tipo][$nombre]+=$m;}
        }
        foreach($grupos as &$g)foreach(['base','igv','total'] as $nombre)$g[$nombre]/=100;unset($g);
        return ['documentos'=>count($filas),'base'=>$c['base']/100,'igv'=>$c['igv']/100,'total'=>$c['total']/100,'grupos'=>array_values($grupos)];
    }
}
