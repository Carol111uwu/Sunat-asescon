<?php
declare(strict_types=1);
require_once __DIR__.'/ConsultaDeclaracionesSunat.php';
require_once __DIR__.'/../../../configuracion/seguridad.php';

                                                                                           
final class ArchivoConsultaOficial
{
    public function __construct(private ?string $base=null) {$this->base??=dirname(rutaClaveMaestraAsescon()).'/declaraciones_consultadas';}
    private function ruta(int $cliente,array $identidad): string
    {
        if ($cliente<1) throw new DomainException('Empresa no válida para el archivo.');
        return $this->base.'/'.$cliente.'/'.hash('sha256',json_encode($identidad,JSON_THROW_ON_ERROR));
    }
    public function obtener(int $cliente,array $identidad,string $extension,string $nombre): ?array
    {
        $ruta=$this->ruta($cliente,$identidad);
        if (!is_file($ruta) || filesize($ruta)>25*1024*1024) return null;
        return ConsultaDeclaracionesSunat::validarArchivo(['body'=>(string)file_get_contents($ruta)],$extension,$nombre);
    }
    public function guardar(int $cliente,array $identidad,array $archivo): void
    {
        $ruta=$this->ruta($cliente,$identidad);$dir=dirname($ruta);
        if (!is_dir($dir) && !mkdir($dir,0700,true) && !is_dir($dir)) throw new DomainException('No se pudo conservar la copia privada del documento.');
        $tmp=tempnam($dir,'documento_');
        if ($tmp===false) throw new DomainException('No se pudo preparar la copia privada del documento.');
        try {
            chmod($tmp,0600);
            if (file_put_contents($tmp,$archivo['body'],LOCK_EX)!==strlen($archivo['body']) || !rename($tmp,$ruta)) throw new DomainException('No se pudo conservar el documento oficial.');
        } finally {if (is_file($tmp)) unlink($tmp);}
    }
}
