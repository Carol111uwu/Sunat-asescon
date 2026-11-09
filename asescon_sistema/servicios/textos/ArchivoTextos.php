<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/configuracion/rutas_privadas.php';
                                                                                              
final class ArchivoTextos
{
    public function __construct(private int $usuario,private ?string $base=null)
    {
        if($usuario<1)throw new InvalidArgumentException('Usuario inválido.');
        $this->base??=asesconRutaAlmacenamiento().'/textos';
    }
    public function listar():array
    {
        $ruta=$this->base.'/'.$this->usuario.'.json';if(!is_file($ruta))return [];
        $d=json_decode((string)file_get_contents($ruta),true,512,JSON_THROW_ON_ERROR);
        if(!is_array($d))throw new RuntimeException('No se pudo leer el archivo de textos.');return $d;
    }
    public function cambiar(string $tipo,int $id,bool $archivar):void
    {
        if(!preg_match('/^[a-z_]{1,40}$/D',$tipo)||$id<1)throw new InvalidArgumentException('Texto inválido.');
        if(!is_dir($this->base)&&!mkdir($this->base,0700,true)&&!is_dir($this->base))throw new RuntimeException('No se pudo preparar el archivo.');
        $ruta=$this->base.'/'.$this->usuario.'.json';$lock=fopen($ruta.'.lock','c+');
        if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Archivo ocupado.');
        try{
            $d=$this->listar();$clave=$tipo.':'.$id;
            if($archivar)$d[$clave]=['tipo'=>$tipo,'id'=>$id,'archivado_en'=>date(DATE_ATOM)];else unset($d[$clave]);
            $tmp=tempnam($this->base,'archivo_');if($tmp===false)throw new RuntimeException('No se pudo guardar.');
            try{$json=json_encode($d,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);if(file_put_contents($tmp,$json)!==strlen($json))throw new RuntimeException('No se pudo guardar.');chmod($tmp,0600);if(!rename($tmp,$ruta))throw new RuntimeException('No se pudo confirmar el archivo.');}finally{if(is_file($tmp))unlink($tmp);}
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
}
