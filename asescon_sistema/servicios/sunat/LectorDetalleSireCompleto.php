<?php
declare(strict_types=1);
                                                                           
final class LectorDetalleSireCompleto
{
    public function leer(string $ruta):array
    {
        if(!is_file($ruta)||filesize($ruta)>25*1024*1024)throw new DomainException('El original no está disponible o excede 25 MB.');
        $f=fopen($ruta,'rb');$filas=[];$ancho=0;$delimitador='';
        try{
            while(($linea=fgets($f))!==false){
                $linea=rtrim($linea,"\r\n");if($linea==='')continue;
                $linea=preg_replace('/^\xEF\xBB\xBF/','',$linea)??$linea;
                if(!preg_match('//u',$linea)){
                    if(!function_exists('iconv'))throw new DomainException('Activa iconv para leer la codificación del original.');
                    $linea=iconv('Windows-1252','UTF-8',$linea);if($linea===false)throw new DomainException('No se pudo leer la codificación del original.');
                }
                if($delimitador===''){
                    $cantidad=0;foreach(['|',"\t",';',','] as $d){$n=count(str_getcsv($linea,$d,'"',''));if($n>$cantidad){$cantidad=$n;$delimitador=$d;}}
                    if($cantidad<4)throw new DomainException('No se reconoció un detalle SIRE delimitado.');
                }
                $fila=str_getcsv($linea,$delimitador,'"','');if(end($fila)==='')array_pop($fila);
                $ancho=max($ancho,count($fila));if($ancho>256||count($filas)>=20001)throw new DomainException('El original supera el límite de 20000 filas o 256 columnas.');$filas[]=$fila;
            }
        }finally{fclose($f);}
        if(!$filas)throw new DomainException('El original está vacío.');
        $primera=implode(' ',array_slice($filas[0],0,8));$cabecera=preg_match('/\bRUC\b/i',$primera)&&preg_match('/period|períod/iu',$primera);
        $cabeceras=$cabecera?array_shift($filas):array_map(static fn(int $n):string=>'Campo original '.$n,range(1,$ancho));
        if(count($filas)>20000)throw new DomainException('El original supera 20000 registros.');
        $cabeceras=array_pad($cabeceras,$ancho,'');foreach($cabeceras as $i=>&$c)if(trim((string)$c)==='')$c='Campo original '.($i+1);unset($c);
        return ['cabeceras'=>$cabeceras,'filas'=>array_map(static fn(array $r):array=>array_pad($r,$ancho,''),$filas),'tipos'=>array_fill(0,$ancho,'text')];
    }
}
