<?php
declare(strict_types=1);

final class LectorContenedorZip
{
    private array $entradas=[];
    public function __construct(private string $contenido)
    {
        if(strlen($contenido)>12*1024*1024)throw new DomainException('El contenedor supera los 12 MB.');
        $fin=strrpos($contenido,"PK\x05\x06");
        if($fin===false||$fin+22>strlen($contenido))throw new DomainException('El archivo ZIP/XLSX está incompleto.');
        $e=unpack('vdisk/vstart/vcountdisk/vcount/Vsize/Voffset/vcomment',substr($contenido,$fin+4,18));
        if($e['disk']||$e['start']||$e['count']>2000||$e['offset']+$e['size']>$fin)throw new DomainException('Contenedor ZIP no compatible.');
        $pos=$e['offset'];$total=0;
        for($i=0;$i<$e['count'];$i++){
            if(substr($contenido,$pos,4)!=="PK\x01\x02"||$pos+46>$fin)throw new DomainException('Índice ZIP inválido.');
            $d=unpack('vversion/vneeded/vflags/vmethod/vtime/vdate/Vcrc/Vcompressed/Vsize/vname/vextra/vcomment/vdisk/vinternal/Vexternal/Voffset',substr($contenido,$pos+4,42));
            $nombre=substr($contenido,$pos+46,$d['name']);$pos+=46+$d['name']+$d['extra']+$d['comment'];
            if($d['flags']&1||!in_array($d['method'],[0,8],true)||$d['size']>24*1024*1024||$pos>$fin)throw new DomainException('El ZIP contiene una entrada no compatible.');
            $total+=$d['size'];if($total>60*1024*1024)throw new DomainException('El contenido descomprimido supera el límite.');
            if(isset($this->entradas[$nombre])||str_contains($nombre,'..')||str_starts_with($nombre,'/'))throw new DomainException('El ZIP contiene nombres duplicados o rutas inválidas.');
            $this->entradas[$nombre]=$d;
        }
    }
    public function nombres():array{return array_keys($this->entradas);}
    public function leer(string $nombre):string
    {
        $d=$this->entradas[$nombre]??throw new DomainException('No se encontró la parte '.$nombre.' del archivo.');
        $p=$d['offset'];
        if(substr($this->contenido,$p,4)!=="PK\x03\x04"||$p+30>strlen($this->contenido))throw new DomainException('Entrada ZIP inválida.');
        $l=unpack('vname/vextra',substr($this->contenido,$p+26,4));$p+=30+$l['name']+$l['extra'];
        if($p+$d['compressed']>strlen($this->contenido))throw new DomainException('Entrada ZIP truncada.');
        $datos=substr($this->contenido,$p,$d['compressed']);
        if($d['method']===8){$datos=@gzinflate($datos,$d['size']+1);}
        if(!is_string($datos)||strlen($datos)!==$d['size']||pack('V',crc32($datos))!==pack('V',$d['crc']))throw new DomainException('La integridad del ZIP no es válida.');
        return $datos;
    }
}
