<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../modelos/sunat/Liquidacion.php';
require_once __DIR__.'/../../servicios/clientes/BusquedaClientes.php';
require_once __DIR__.'/../../servicios/sunat/ExportadorExcelSire.php';
require_once __DIR__.'/../../servicios/documentos/GeneradorPdfTabla.php';
require_once __DIR__.'/../../servicios/sunat/PeriodosSire.php';
asesconExigirPermiso($conexion,'LIQUIDACIONES','ver');
$periodo=(string)($_GET['periodo']??'');$id=(int)($_GET['id_cliente']??0);$formato=(string)($_GET['formato']??'pdf');
if(!PeriodosSire::permitido($periodo)||!in_array($formato,['pdf','xlsx'],true)){http_response_code(400);exit('Formato o período no válido.');}
if($id>0&&!asesconPuedeVerCliente($conexion,$id)){http_response_code(403);exit('Cliente no autorizado.');}
$filtro=asesconFiltroClientesSql($conexion,'c');if($id>0)$filtro.=' AND c.id_cliente='.$id;
$datos=(new Liquidacion($conexion))->listar($filtro,$periodo);$q=(string)($_GET['q']??'');$modo=(string)($_GET['modo']??'');
$datos=array_values(array_filter($datos,static fn(array $d):bool=>BusquedaClientes::coincide($d,$q)&&($modo==='pagar'?(float)$d['igv_pagar_sf_mostrar']>0:($modo==='favor'?(float)$d['igv_pagar_sf_mostrar']<0:true))));
$cabeceras=['Cliente','RUC','Período','IGV resultante','Crédito anterior','IGV pagar / SF','Renta','Otros tributos','Total pagar'];$filas=[];
foreach($datos as $d){$otros=(float)$d['total']-max(0,(float)$d['igv'])-(float)$d['renta'];$filas[]=[(string)$d['razon_social'],(string)$d['ruc'],(string)$d['periodo'],(float)$d['impuesto_resultado_mostrar'],(float)$d['credito_fiscal_anterior_mostrar'],(float)$d['igv_pagar_sf_mostrar'],(float)$d['renta'],round($otros,2),(float)$d['total']];}
$tipos=['text','text','text','money','money','money','money','money','money'];$anchos=[198,77,51,70,70,80,65,71,80];
if($id>0&&substr($periodo,4,2)!=='00'&&count($datos)===1){
    $d=$datos[0];$obs=(string)($d['observaciones']??'');$calculo=str_starts_with($obs,"ASESCON_LIQ_V1\n")?(json_decode(substr($obs,strlen("ASESCON_LIQ_V1\n")),true)?:[]):[];
    $cabeceras=['Concepto','Importe / valor','Información'];$tipos=['text','text','text'];$anchos=[240,120,402];
    $filas=[['Cliente',(string)$d['ruc'],(string)$d['razon_social']],['Período',$periodo,'Estado: '.(string)$d['estado']],['Fecha de liquidación',(string)($d['fecha_liquidacion']??''),'Importes guardados y revisados por el usuario']];
    if($calculo){
        foreach(['resumen_ventas'=>'RVIE · Ventas','resumen_compras'=>'RCE · Compras'] as $clave=>$label)foreach(($calculo[$clave]['grupos']??[]) as $g)$filas[]=[$label.' · tipo '.$g['tipo'],number_format((float)$g['total'],2,'.',','),'Documentos: '.(int)$g['documentos'].' · Base: '.number_format((float)$g['base'],2,'.',',').' · IGV: '.number_format((float)$g['igv'],2,'.',',')];
        foreach(['igv_ventas'=>'IGV de ventas','credito_compras'=>'Crédito fiscal aplicable de compras','credito_fiscal_anterior'=>'Crédito fiscal anterior','percepciones'=>'Percepciones aplicables','retenciones'=>'Retenciones aplicables','impuesto_resultado'=>'IGV resultante','igv_pagar_sf'=>'IGV pagar / saldo a favor','renta'=>'Renta del período','saldo_anterior'=>'Saldo anterior de renta','renta_pagar'=>'Renta por pagar','essalud'=>'EsSalud','onp'=>'ONP','renta_quinta'=>'Renta de quinta categoría','itan'=>'ITAN por pagar','otros'=>'Otros conceptos','total_pagar'=>'Total por pagar'] as $clave=>$label)$filas[]=[$label,number_format((float)($calculo[$clave]??0),2,'.',','),'Soles'];
        $notas=(string)($calculo['notas']??'');
    }else{
        foreach(['impuesto_resultado_mostrar'=>'IGV resultante','credito_fiscal_anterior_mostrar'=>'Crédito fiscal anterior','igv_pagar_sf_mostrar'=>'IGV pagar / saldo a favor','renta'=>'Renta por pagar','essalud'=>'EsSalud','onp'=>'ONP','total'=>'Total por pagar'] as $clave=>$label)$filas[]=[$label,number_format((float)($d[$clave]??0),2,'.',','),'Registro anterior; desglose limitado a sus campos guardados'];
        $notas=$obs;
    }
    $caracteres=preg_split('//u',$notas,-1,PREG_SPLIT_NO_EMPTY)?:[];foreach(array_chunk($caracteres,110) as $parte)$filas[]=['Observaciones','',implode('',$parte)];
}
$titulo='Liquidación de impuestos · '.$periodo;$subtitulo='Importes guardados en ASESCON · Generado: '.date('d/m/Y H:i');
if($formato==='xlsx'){$bytes=(new ExportadorExcelSire())->crear($titulo,$subtitulo,$cabeceras,$filas,$tipos,'Liquidaciones');header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');}
else{$pdfFilas=array_map(static fn(array $f):array=>array_map(static fn($v):string=>is_float($v)?number_format($v,2,'.',','):(string)$v,$f),$filas);$bytes=(new GeneradorPdfTabla())->crear($titulo,$subtitulo,$cabeceras,$pdfFilas,$anchos);header('Content-Type: application/pdf');}
header('Content-Disposition: '.($formato==='pdf'&&empty($_GET['descargar'])?'inline':'attachment').'; filename="liquidacion_'.$periodo.'.'.$formato.'"');header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');header('Content-Length: '.strlen($bytes));echo $bytes;
