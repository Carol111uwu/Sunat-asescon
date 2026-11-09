<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/configuracion/rutas_privadas.php';
require_once __DIR__.'/ServicioEstadoAdjuntosSol.php';
require_once __DIR__.'/ServicioPrecargaAdjuntosSol.php';
require_once __DIR__.'/../archivos/ServicioArchivosPrivados.php';
require_once __DIR__.'/../../modelos/buzones/RepositorioDocumentoBuzon.php';

final class ServicioResultadoAdjuntosSol
{
    public function __construct(private mysqli $conexion){}
    public function guardar(int $cliente,array $resultados,array $entrada):int
    {
        $permitidos=[];foreach($entrada['documentos_adjuntos']??[] as $d)$permitidos[(int)$d['id_documento']]=true;
        $archivos=new ServicioArchivosPrivados();$estados=new ServicioEstadoAdjuntosSol();$repo=new RepositorioDocumentoBuzon($this->conexion);$guardados=0;$vistos=[];
        foreach($resultados as $d){
            $id=(int)($d['id_documento']??0);
            if(!isset($permitidos[$id])||isset($vistos[$id]))throw new RuntimeException('El lote de PDF contiene una notificación ajena o repetida.');
            $vistos[$id]=true;
            if(empty($d['ok'])){
                $codigo=(string)($d['codigo']??'SOL_PDF_NO_DISPONIBLE');
                $mensajes=['SOL_DOCUMENTO_NO_IDENTIFICADO'=>'No se pudo localizar una única notificación por asunto y fecha. Se reintentará.', 'SOL_SESION_VENCIDA'=>'La sesión SOL venció durante la descarga. Se reintentará.', 'SOL_CONTROL_DOCUMENTO_NO_DISPONIBLE'=>'No se encontró el botón del documento en la bandeja SOL. Se reintentará.'];
                $estados->registrar($cliente,$id,false,$mensajes[$codigo]??'SUNAT no entregó este PDF. Se reintentará automáticamente.',preg_match('/^[A-Z0-9_]{1,80}$/D',$codigo)?$codigo:'SOL_PDF_NO_DISPONIBLE');continue;
            }
            try{
                $registro=$repo->obtener('SOL',$id,'c.id_cliente='.(int)$cliente);
                if(!$registro)throw new RuntimeException('La notificación ya no pertenece a este cliente.');
                $archivo=$archivos->resolver((string)($d['archivo']??''),'SOL');
                $esperado=realpath(asesconRutaAlmacenamiento().'/sunat/buzon_sol/adjuntos/'.$cliente.'/'.$id);
                if(!$archivo||!$esperado||!str_starts_with($archivo,$esperado.DIRECTORY_SEPARATOR))throw new RuntimeException('El archivo no pertenece al cliente y documento del lote.');
                $referencia=$archivos->guardarArchivo($archivo,'SOL',$cliente,$id);
                $repo->vincular('SOL',$id,$referencia,'c.id_cliente='.(int)$cliente);
                $estados->registrar($cliente,$id,true);$guardados++;
                if(is_file($archivo)&&basename($archivo)!==basename($referencia))unlink($archivo);
            }catch(Throwable $e){error_log('Guardar adjunto SOL '.$id.': '.get_class($e));$estados->registrar($cliente,$id,false,'No se pudo guardar este PDF. Se conservaron los documentos anteriores y se reintentará.');}
        }
        return $guardados;
    }
}
