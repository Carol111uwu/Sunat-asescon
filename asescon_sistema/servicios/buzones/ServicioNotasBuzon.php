<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/configuracion/rutas_privadas.php';

final class ServicioNotasBuzon
{
    public function __construct(private ?string $raiz=null)
    {
        $this->raiz??=asesconRutaAlmacenamiento().'/buzones/notas';
    }
    private function ruta(string $buzon,int $cliente,int $documento):string
    {
        if(!in_array($buzon,['SOL','SUNAFIL'],true)||$cliente<1||$documento<1)throw new DomainException('Notificación no válida para registrar notas.');
        return $this->raiz.'/'.strtolower($buzon).'/'.$cliente.'/'.$documento.'.json';
    }
    public function listar(string $buzon,int $cliente,int $documento):array
    {
        $ruta=$this->ruta($buzon,$cliente,$documento);
        if(!is_file($ruta))return [];
        $notas=json_decode((string)file_get_contents($ruta),true,512,JSON_THROW_ON_ERROR);
        if(!is_array($notas))throw new RuntimeException('No se pudo leer el seguimiento de la notificación.');
        foreach($notas as $i=>&$n){$n['id_nota']=$documento*100000+$i+1;}unset($n);
        return array_values(array_filter($notas,static fn($n)=>($n['texto']??'')!==''));
    }
    public function agregar(string $buzon,int $cliente,int $documento,int $usuario,string $nombre,string $texto,string $clave):array
    {
        $texto=trim($texto);
        if($usuario<1||$texto===''||preg_match('//u',$texto)!==1||strlen($texto)>20000||preg_match_all('/./us',$texto)>5000)throw new DomainException('Escribe una nota de hasta 5.000 caracteres.');
        if(!preg_match('/^[a-zA-Z0-9_-]{16,80}$/D',$clave))throw new DomainException('Vuelve a abrir la notificación antes de guardar la nota.');
        $ruta=$this->ruta($buzon,$cliente,$documento);$directorio=dirname($ruta);
        if(!is_dir($directorio)&&!mkdir($directorio,0700,true)&&!is_dir($directorio))throw new RuntimeException('No se pudo preparar el seguimiento.');
        $candado=fopen($ruta.'.lock','c+');if(!$candado)throw new RuntimeException('No se pudo bloquear el seguimiento.');
        try{
            if(!flock($candado,LOCK_EX))throw new RuntimeException('No se pudo bloquear el seguimiento.');
            $notas=is_file($ruta)?json_decode((string)file_get_contents($ruta),true,512,JSON_THROW_ON_ERROR):[];
            if(count($notas)>=99999)throw new DomainException('Esta notificación alcanzó el límite de notas.');
            foreach($notas as $nota){if(($nota['clave']??'')===$clave&&(int)$nota['id_usuario']===$usuario)return $this->listar($buzon,$cliente,$documento);}
            $notas[]=['clave'=>$clave,'id_usuario'=>$usuario,'autor'=>$nombre,'texto'=>$texto,'fecha'=>date(DATE_ATOM)];
            $json=json_encode($notas,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);$temporal=tempnam($directorio,'nota_');
            if($temporal===false)throw new RuntimeException('No se pudo guardar la nota.');
            try{if(file_put_contents($temporal,$json,LOCK_EX)!==strlen($json))throw new RuntimeException('No se pudo guardar la nota.');chmod($temporal,0600);if(!rename($temporal,$ruta))throw new RuntimeException('No se pudo guardar la nota.');}
            finally{if(is_file($temporal))unlink($temporal);}
            return $this->listar($buzon,$cliente,$documento);
        }finally{flock($candado,LOCK_UN);fclose($candado);}
    }
    public function eliminar(string $buzon,int $cliente,int $documento,int $nota,string $revision):void
    {
        $ruta=$this->ruta($buzon,$cliente,$documento);$lock=fopen($ruta.'.lock','c+');
        if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('No se pudo bloquear la nota.');
        try{
            $notas=json_decode((string)file_get_contents($ruta),true,512,JSON_THROW_ON_ERROR);$indice=$nota-$documento*100000-1;
            if($indice<0||!isset($notas[$indice])||!hash_equals(hash('sha256',(string)$notas[$indice]['texto']),$revision))throw new DomainException('La nota cambió. Recarga antes de eliminar.');
            $notas[$indice]['texto']='';$notas[$indice]['eliminada_en']=date(DATE_ATOM);
            $tmp=tempnam(dirname($ruta),'nota_');if($tmp===false)throw new RuntimeException('No se pudo guardar.');
            try{$json=json_encode($notas,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);if(file_put_contents($tmp,$json)!==strlen($json))throw new RuntimeException('No se pudo guardar.');chmod($tmp,0600);if(!rename($tmp,$ruta))throw new RuntimeException('No se pudo guardar.');}finally{if(is_file($tmp))unlink($tmp);}
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    public function editar(string $buzon,int $cliente,int $documento,int $nota,string $revision,string $texto):void
    {
        $texto=trim($texto);
        if($texto===''||strlen($texto)>20000||preg_match('//u',$texto)!==1)throw new DomainException('Nota inválida.');
        $ruta=$this->ruta($buzon,$cliente,$documento);$lock=fopen($ruta.'.lock','c+');
        if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('No se pudo bloquear la nota.');
        try{
            $notas=json_decode((string)file_get_contents($ruta),true,512,JSON_THROW_ON_ERROR);$indice=$nota-$documento*100000-1;
            if($indice<0||!isset($notas[$indice])||!hash_equals(hash('sha256',(string)$notas[$indice]['texto']),$revision))throw new DomainException('La nota cambió. Recarga antes de editar.');
            $notas[$indice]['texto']=$texto;$notas[$indice]['editada_en']=date(DATE_ATOM);
            $tmp=tempnam(dirname($ruta),'nota_');if($tmp===false)throw new RuntimeException('No se pudo guardar.');
            try{$json=json_encode($notas,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);if(file_put_contents($tmp,$json)!==strlen($json))throw new RuntimeException('No se pudo guardar.');chmod($tmp,0600);if(!rename($tmp,$ruta))throw new RuntimeException('No se pudo guardar.');}finally{if(is_file($tmp))unlink($tmp);}
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }

}
