<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/configuracion/rutas_privadas.php';

final class ServicioEstadoAdjuntosSol
{
    public function __construct(private ?string $raiz=null){$this->raiz??=asesconRutaAlmacenamiento().'/sunat/buzon_sol/estado_adjuntos';}
    private function ruta(int $cliente,int $documento):string
    {
        if($cliente<1||$documento<1)throw new InvalidArgumentException('Identidad de adjunto no válida.');
        return $this->raiz.'/'.$cliente.'/'.$documento.'.json';
    }
    public function obtener(int $cliente,int $documento):array
    {
        $ruta=$this->ruta($cliente,$documento);
        if(!is_file($ruta))return ['estado'=>'PENDIENTE','mensaje'=>'El PDF se obtiene automáticamente durante la sincronización.'];
        $datos=json_decode((string)file_get_contents($ruta),true);
        return is_array($datos)?$datos:['estado'=>'PENDIENTE'];
    }
    public function puedeIntentar(int $cliente,int $documento):bool
    {
        $estado=$this->obtener($cliente,$documento);
        return (($estado['version_conector']??'')!=='20261027_1') || (int)($estado['reintentar_desde']??0)<=time();
    }
    public function registrar(int $cliente,int $documento,bool $correcto,string $mensaje='',string $codigo=''):void
    {
        $ruta=$this->ruta($cliente,$documento);$directorio=dirname($ruta);
        if(!is_dir($directorio)&&!mkdir($directorio,0700,true)&&!is_dir($directorio))throw new RuntimeException('No se pudo preparar el estado de adjuntos.');
        $datos=['version_conector'=>'20261027_1','codigo'=>$codigo,'estado'=>$correcto?'LISTO':'ERROR','mensaje'=>$correcto?'PDF guardado en ASESCON.':$mensaje,'actualizado_en'=>date(DATE_ATOM),'reintentar_desde'=>$correcto?0:time()+900];
        $json=json_encode($datos,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);$tmp=tempnam($directorio,'estado_');
        if($tmp===false)throw new RuntimeException('No se pudo registrar el estado de adjuntos.');
        try{if(file_put_contents($tmp,$json,LOCK_EX)!==strlen($json))throw new RuntimeException('No se pudo guardar el estado de adjuntos.');chmod($tmp,0600);if(!rename($tmp,$ruta))throw new RuntimeException('No se pudo guardar el estado de adjuntos.');}
        finally{if(is_file($tmp))unlink($tmp);}
    }
}
