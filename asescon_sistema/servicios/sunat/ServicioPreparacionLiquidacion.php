<?php
declare(strict_types=1);
require_once __DIR__.'/../../modelos/sunat/RepositorioLecturaSire.php';
require_once __DIR__.'/../../modelos/sunat/Liquidacion.php';
require_once __DIR__.'/CalculoLiquidacion.php';

final class ServicioPreparacionLiquidacion
{
    public function __construct(private mysqli $conexion){}
    public function preparar(int $id,string $periodo,bool $recargar=false):array
    {
        if(!preg_match('/^20\d{2}(0[1-9]|1[0-2])$/D',$periodo))throw new DomainException('Selecciona un mes para preparar una liquidación. La vista anual reúne las liquidaciones guardadas.');
        $repo=new RepositorioLecturaSire($this->conexion);$cliente=$repo->obtenerCliente($id);
        if(!$cliente)throw new DomainException('Cliente no encontrado.');
        $ventas=$repo->obtenerDetallesNormalizados('RVIE',$id,$periodo);$compras=$repo->obtenerDetallesNormalizados('RCE',$id,$periodo);
        $rvie=$repo->obtenerCabeceraPeriodo('RVIE',$id,$periodo);$rce=$repo->obtenerCabeceraPeriodo('RCE',$id,$periodo);
        $fuentes=['ventas'=>$rvie['id_rvie']>0,'compras'=>$rce['id_rce']>0];
        $resumenVentas=CalculoLiquidacion::totalesSire($ventas);$resumenCompras=CalculoLiquidacion::totalesSire($compras);
        $guardada=(new Liquidacion($this->conexion))->obtener($id,$periodo);$datos=[];
        if($guardada&&!$recargar){
            $obs=(string)($guardada['observaciones']??'');$marca="ASESCON_LIQ_V1\n";
            if(str_starts_with($obs,$marca)){$datos=json_decode(substr($obs,strlen($marca)),true)?:[];}
            if(!$datos){
                $saldoNeto=(float)($guardada['igv_pagar_sf']??$guardada['igv']??0);
                if($saldoNeto==0.0&&(float)($guardada['igv']??0)!=0.0)$saldoNeto=(float)$guardada['igv'];
                $bruto=$saldoNeto+(float)($guardada['credito_fiscal_anterior']??0);
                $ventasPropuestas=($fuentes['ventas']&&$resumenVentas['documentos']>0)?$resumenVentas['igv']:max(0,$bruto);
                $datos=['igv_ventas'=>$ventasPropuestas,'credito_compras'=>$ventasPropuestas-$bruto,'renta'=>(float)($guardada['renta']??0)+(float)($guardada['saldo_anterior']??0),'essalud'=>$guardada['essalud'],'onp'=>$guardada['onp'],'saldo_anterior'=>$guardada['saldo_anterior'],'credito_fiscal_anterior'=>$guardada['credito_fiscal_anterior'],'notas'=>$obs];
            }
        }else $datos=['igv_ventas'=>$resumenVentas['igv'],'credito_compras'=>$resumenCompras['igv']];
        $calculo=CalculoLiquidacion::calcular($datos);
        return ['cliente'=>$cliente,'periodo'=>$periodo,'fuentes'=>$fuentes,'ventas'=>$ventas,'compras'=>$compras,'resumen_ventas'=>$resumenVentas,'resumen_compras'=>$resumenCompras,'calculo'=>$calculo,'guardada'=>$guardada!==null,'notas'=>(string)($datos['notas']??''),'estado'=>$guardada['estado']??'BORRADOR','requiere_revision_legacy'=>$guardada&&!str_starts_with((string)($guardada['observaciones']??''),"ASESCON_LIQ_V1\n")];
    }
    public function guardar(int $id,string $periodo,array $entrada):void
    {
        $preparada=$this->preparar($id,$periodo);
        if(empty($entrada['confirmacion_revision']))throw new DomainException('Confirma la revisión del crédito fiscal y demás tributos antes de guardar.');
        if((!$preparada['fuentes']['ventas']||!$preparada['fuentes']['compras'])&&empty($entrada['confirmacion_manual']))throw new DomainException('Falta RVIE o RCE de este período. Sincroniza los libros o confirma que registrarás los importes manualmente.');
        $calculo=CalculoLiquidacion::calcular($entrada);$calculo['notas']=trim((string)($entrada['notas']??''));
        if(strlen($calculo['notas'])>10000)throw new DomainException('Las observaciones son demasiado extensas.');
        $calculo['resumen_ventas']=$preparada['resumen_ventas'];$calculo['resumen_compras']=$preparada['resumen_compras'];
        $estado=in_array((string)($entrada['estado']??''),['PENDIENTE','REVISADA','PAGADA'],true)?$entrada['estado']:'PENDIENTE';
        (new Liquidacion($this->conexion))->guardar(['id_cliente'=>$id,'periodo'=>$periodo,'igv'=>$calculo['igv'],'renta'=>$calculo['renta_pagar'],'essalud'=>$calculo['essalud'],'onp'=>$calculo['onp'],'total'=>$calculo['total_pagar'],'impuesto_resultado'=>$calculo['impuesto_resultado'],'saldo_anterior'=>$calculo['saldo_anterior'],'credito_fiscal_anterior'=>$calculo['credito_fiscal_anterior'],'igv_pagar_sf'=>$calculo['igv_pagar_sf'],'estado'=>$estado,'fecha_liquidacion'=>date('Y-m-d'),'observaciones'=>"ASESCON_LIQ_V1\n".json_encode($calculo,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
    }
}
