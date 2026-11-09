<?php
declare(strict_types=1);
require_once __DIR__.'/../archivos/LectorHojaXlsx.php';
require_once __DIR__.'/ClaveComprobante.php';
final class LectorListaComprobantes
{
    public function leer(string $archivo,string $extension,int $cliente):array
    {
        if(!is_file($archivo)||filesize($archivo)>8*1024*1024)throw new DomainException('La lista debe pesar como máximo 8 MB.');
        if($extension==='xlsx')$filas=(new LectorHojaXlsx())->leer((string)file_get_contents($archivo));
        elseif($extension==='csv'){
            $texto=preg_replace('/^\xEF\xBB\xBF/','',(string)file_get_contents($archivo));$lineas=preg_split('/\r\n|\n|\r/',$texto);$d=substr_count($lineas[0]??'', ';')>substr_count($lineas[0]??'',',')?';':',';$filas=array_map(static fn(string $l):array=>str_getcsv($l,$d,'"',''),array_filter($lineas,static fn(string $l):bool=>trim($l)!==''));
        }else throw new DomainException('Usa CSV o XLSX.');
        $mapa=null;$salida=[];
        foreach($filas as $i=>$fila){
            if($mapa===null){$m=[];foreach($fila as $n=>$campo){$campo=strtolower(strtr(trim((string)$campo),['ú'=>'u','Ú'=>'u','ó'=>'o','Ó'=>'o','í'=>'i','Í'=>'i','é'=>'e','É'=>'e','á'=>'a','Á'=>'a']));$campo=preg_replace('/[_\s]+/',' ',$campo);$k=match($campo){'ruc emisor','ruc proveedor'=>'ruc_emisor','tipo documento','tipo comprobante','tipo'=>'tipo_documento','serie'=>'serie','numero','numero documento'=>'numero',default=>null};if($k)$m[$k]=$n;}if(count($m)===4)$mapa=$m;continue;}
            $d=['id_cliente'=>$cliente];foreach($mapa as $campo=>$n)$d[$campo]=$fila[$n]??'';$d=ClaveComprobante::normalizar($d);$salida[ClaveComprobante::identificador($d)]=$d;
            if(count($salida)>500)throw new DomainException('Procesa listas de hasta 500 comprobantes.');
        }
        if(!$mapa||!$salida)throw new DomainException('La lista no contiene comprobantes. Usa la plantilla de RUC emisor, tipo, serie y número.');
        return array_values($salida);
    }
}
