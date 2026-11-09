<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/configuracion/rutas_privadas.php';

final class ServicioArchivosPrivados
{
    public const CARPETAS=[
        'SOL'=>'almacenamiento/sunat/buzon_sol/adjuntos',
        'SUNAFIL'=>'almacenamiento/sunafil/buzon_sunafil/adjuntos',
        'COMPROBANTES'=>'almacenamiento/sunat/comprobantes',
        'DECLARACIONES'=>'almacenamiento/sunat/declaraciones',
    ];
    public function __construct(private ?string $raiz=null){$this->raiz??=asesconRaizDatosPrivados();}
    public function resolver(string $referencia,string $modulo):?string
    {
        $carpeta=self::CARPETAS[$modulo]??throw new InvalidArgumentException('Módulo de archivos inválido.');
        $base=realpath($this->raiz.'/'.$carpeta);
        if(!$base||$referencia===''||str_contains($referencia,"\0")||preg_match('~^[a-z][a-z0-9+.-]*:~i',$referencia))return null;
                                                                                  
                                                                               
                                                                                  
        $referencia=str_replace('\\','/',$referencia);
        if(str_starts_with($referencia,'/')){
            $marca=strpos($referencia,'/almacenamiento/');
            if($marca===false)return null;
            $referencia=substr($referencia,$marca+1);
        }
        $ruta=realpath($this->raiz.'/'.ltrim($referencia,'/'));
        if(!$ruta||!str_starts_with($ruta,$base.DIRECTORY_SEPARATOR)||!is_file($ruta)||!is_readable($ruta))return null;
        return $ruta;
    }
    public function guardarSubida(array $archivo,string $modulo,int $cliente,int $documento,string $tipo='PDF'):string
    {
        if(in_array((int)($archivo['error']??0),[UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE],true))throw new DomainException('El servidor rechazó el archivo por tamaño. Su límite de carga actual es '.ini_get('upload_max_filesize').'.');
        if(($archivo['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file((string)($archivo['tmp_name']??'')))throw new DomainException('El archivo no terminó de cargarse.');
        return $this->guardarArchivo((string)$archivo['tmp_name'],$modulo,$cliente,$documento,$tipo);
    }
    public function guardarArchivo(string $origen,string $modulo,int $cliente,int $documento,string $tipo='PDF'):string
    {
        $base=self::CARPETAS[$modulo]??throw new InvalidArgumentException('Módulo de archivos inválido.');
        if($cliente<1||$documento<1||!is_file($origen)||!is_readable($origen)||filesize($origen)<1)throw new DomainException('El archivo no está disponible o está vacío.');
        $tipo=strtoupper($tipo);
        if($tipo==='PDF')return $this->guardarPdfPorFlujo($origen,$base,$cliente,$documento);
        $bytes=(string)file_get_contents($origen);
        $extension=match($tipo){'PDF'=>'pdf','XML'=>'xml','CDR'=>'zip',default=>throw new DomainException('Tipo de archivo no válido.')};
        if($tipo==='PDF'&&!str_starts_with($bytes,'%PDF-'))throw new DomainException('El archivo no es un PDF válido.');
        $xml=preg_replace('/^\xEF\xBB\xBF/','',$bytes)??$bytes;
        if($tipo==='XML'&&(!preg_match('/^\s*(?:<\?xml[^>]*>\s*)?<[^>]+>/s',$xml)||preg_match('/<!DOCTYPE|<!ENTITY/i',$bytes)))throw new DomainException('El XML no es válido o contiene entidades externas.');
        if($tipo==='CDR'){
            require_once __DIR__.'/LectorContenedorZip.php';$zip=new LectorContenedorZip($bytes);$xmls=array_filter($zip->nombres(),static fn(string $n):bool=>str_ends_with(strtolower($n),'.xml'));
            if(!$xmls)throw new DomainException('El CDR ZIP debe contener un XML.');
            foreach($xmls as $nombreXml){$contenidoXml=$zip->leer($nombreXml);if(preg_match('/<!DOCTYPE|<!ENTITY/i',$contenidoXml))throw new DomainException('El XML del CDR contiene entidades no permitidas.');}
        }
        $directorio=$this->raiz.'/'.$base.'/'.$cliente.'/'.$documento;
        if(!is_dir($directorio)&&!mkdir($directorio,0700,true)&&!is_dir($directorio))throw new RuntimeException('No se pudo crear la carpeta de documentos.');
        $nombre=strtolower($tipo).'_'.hash('sha256',$bytes).'.'.$extension;$destino=$directorio.'/'.$nombre;
        if(!is_file($destino)){
            $temporal=tempnam($directorio,'carga_');if($temporal===false)throw new RuntimeException('No se pudo preparar el archivo.');
            try{if(file_put_contents($temporal,$bytes,LOCK_EX)!==strlen($bytes)||!rename($temporal,$destino))throw new RuntimeException('No se pudo guardar el documento.');chmod($destino,0600);}finally{if(is_file($temporal))unlink($temporal);}
        }
        return $base.'/'.$cliente.'/'.$documento.'/'.$nombre;
    }
    private function guardarPdfPorFlujo(string $origen,string $base,int $cliente,int $documento):string
    {
        $f=fopen($origen,'rb');
        if(!$f)throw new DomainException('No se pudo abrir el archivo.');
        try{$cabecera=fread($f,5);}finally{fclose($f);}
        if($cabecera!=='%PDF-')throw new DomainException('El archivo recibido no es un PDF válido.');
        $directorio=$this->raiz.'/'.$base.'/'.$cliente.'/'.$documento;
        if(!is_dir($directorio)&&!mkdir($directorio,0700,true)&&!is_dir($directorio))throw new RuntimeException('No se pudo crear la carpeta privada.');
                                                                                         
        $hash=hash_file('sha256',$origen);
        if(!$hash)throw new RuntimeException('No se pudo verificar el archivo.');
        $nombre='pdf_'.$hash.'.pdf';$destino=$directorio.'/'.$nombre;
        if(!is_file($destino)){
            $origenReal=realpath($origen);
            $directorioReal=realpath($directorio);
                                                                                        
                                                                                      
            if($origenReal && $directorioReal && dirname($origenReal)===$directorioReal){
                if(!rename($origen,$destino))throw new RuntimeException('No se pudo finalizar el PDF descargado.');
                chmod($destino,0600);
            }else{
                $temporal=tempnam($directorio,'carga_');
                if($temporal===false)throw new RuntimeException('No hay almacenamiento temporal disponible.');
                try{
                    if(!copy($origen,$temporal)||filesize($temporal)!==filesize($origen)||!rename($temporal,$destino))throw new RuntimeException('No se pudo almacenar el PDF completo. Comprueba el espacio disponible.');
                    chmod($destino,0600);
                }finally{if(is_file($temporal))unlink($temporal);}
            }
        }
        return $base.'/'.$cliente.'/'.$documento.'/'.$nombre;
    }

    public function servir(string $ruta,bool $descargar=false,string $nombre='documento.pdf'):never
    {
        $tamano=filesize($ruta);$inicio=0;$fin=$tamano-1;$rango=(string)($_SERVER['HTTP_RANGE']??'');
        $extension=strtolower(pathinfo($ruta,PATHINFO_EXTENSION));
        $tipo=match($extension){'pdf'=>'application/pdf','xml'=>'application/xml','zip'=>'application/zip',default=>'application/octet-stream'};
        header('Content-Type: '.$tipo);header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');header("Content-Security-Policy: frame-ancestors 'self'");header('Accept-Ranges: bytes');
        $nombre=preg_replace('/[^A-Za-z0-9_.-]/','_',basename($nombre))?:'documento.'.$extension;
        header('Content-Disposition: '.($descargar?'attachment':'inline').'; filename="'.$nombre.'"');
        if($rango!==''){
            if(!preg_match('/^bytes=(\d*)-(\d*)$/D',$rango,$m)||($m[1]===''&&$m[2]==='')){http_response_code(416);header('Content-Range: bytes */'.$tamano);exit;}
            if($m[1]===''){$inicio=max(0,$tamano-(int)$m[2]);}else{$inicio=(int)$m[1];if($m[2]!=='')$fin=min($fin,(int)$m[2]);}
            if($inicio>$fin||$inicio>=$tamano){http_response_code(416);header('Content-Range: bytes */'.$tamano);exit;}
            http_response_code(206);header('Content-Range: bytes '.$inicio.'-'.$fin.'/'.$tamano);
        }
        header('Content-Length: '.($fin-$inicio+1));
        if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
        if(($_SERVER['REQUEST_METHOD']??'GET')==='HEAD')exit;
        $f=fopen($ruta,'rb');fseek($f,$inicio);$restante=$fin-$inicio+1;
        while($restante>0&&!feof($f)){$parte=fread($f,min(65536,$restante));if($parte===false||$parte==='')break;echo $parte;flush();$restante-=strlen($parte);if(connection_aborted())break;}
        fclose($f);exit;
    }
}
