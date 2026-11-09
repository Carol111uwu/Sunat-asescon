<?php
declare(strict_types=1);
final class GeneradorContenedorZip
{
    public function crear(array $entradas):string
    {
        if(count($entradas)>1000)throw new DomainException('El ZIP supera los 1000 archivos.');
        $local='';$central='';$offset=0;$total=0;
        foreach($entradas as $nombre=>$bytes){
            if(!preg_match('~^[A-Za-z0-9_./-]+$~D',$nombre)||str_contains($nombre,'..')||str_starts_with($nombre,'/'))throw new DomainException('Nombre de archivo ZIP inválido.');
            $total+=strlen($bytes);if($total>50*1024*1024)throw new DomainException('El lote supera los 50 MB. Reduce la selección de documentos.');
            $comprimido=gzdeflate($bytes,6);$crc=crc32($bytes);$n=strlen($nombre);$c=strlen($comprimido);$s=strlen($bytes);
            $parte="PK\x03\x04".pack('vvvvvVVVvv',20,0,8,0,33,$crc,$c,$s,$n,0).$nombre.$comprimido;
            $central.="PK\x01\x02".pack('vvvvvvVVVvvvvvVV',20,20,0,8,0,33,$crc,$c,$s,$n,0,0,0,0,0,$offset).$nombre;
            $local.=$parte;$offset+=strlen($parte);
        }
        $cantidad=count($entradas);return $local.$central."PK\x05\x06".pack('vvvvVVv',0,0,$cantidad,$cantidad,strlen($central),strlen($local),0);
    }
}
