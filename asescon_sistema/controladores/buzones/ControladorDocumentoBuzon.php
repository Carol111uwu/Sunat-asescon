<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../modelos/buzones/RepositorioDocumentoBuzon.php';
require_once __DIR__.'/../../servicios/archivos/ServicioArchivosPrivados.php';
require_once __DIR__.'/../../servicios/sunat/ServicioCorreoBuzonSol.php';
require_once __DIR__.'/../../servicios/casos_sunat/AccesoCasosSol.php';

final class ControladorDocumentoBuzon
{
    public function __construct(private mysqli $conexion){}
    public function procesar(array $p,array $archivos):void
    {
        $buzon=strtoupper((string)($p['buzon']??''));$permiso=RepositorioDocumentoBuzon::permiso($buzon);
        asesconExigirPermiso($this->conexion,$permiso,'editar');
        $token=$buzon==='SOL'?'csrf_buzon_sol':'csrf_buzon_sunafil';
        if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||!asesconValidarCsrf((string)($p['csrf_token']??''),$token)){http_response_code(403);exit('Solicitud no válida.');}
        $ruta=$buzon==='SOL'?'vistas/buzones/buzon_sol_sunat.php':'vistas/buzones/buzon_sunafil.php';
        try{
            $filtro=asesconFiltroClientesSql($this->conexion,'c');if($buzon==='SOL')$filtro=AccesoCasosSol::filtro($this->conexion,$filtro);$repo=new RepositorioDocumentoBuzon($this->conexion);$id=(int)($p['id_documento']??0);
            $d=$repo->obtener($buzon,$id,$filtro);if(!$d)throw new DomainException('La notificación no está autorizada.');
            $accion=(string)($p['accion']??'');
            if(in_array($accion,['obtener_pdf','consultar_detalle','adjuntar_pdf'],true)&&(int)($d['id_cliente']??0)<1)throw new DomainException('Asocia la notificación a un cliente antes de consultar su documento.');
            if($accion==='obtener_pdf'&&$buzon==='SOL'){
                require_once __DIR__.'/../../servicios/sunat/ServicioColaBuzonSol.php';
                (new ServicioColaBuzonSol())->solicitar((int)$d['id_cliente'],(int)$_SESSION['id_usuario'],$id);
                $mensaje='Descarga solicitada. El documento aparecerá cuando SUNAT lo entregue.';
            }elseif($accion==='consultar_detalle'&&$buzon==='SOL'){
                require_once __DIR__.'/../../servicios/sunat/ServicioColaBuzonSol.php';
                require_once __DIR__.'/../../servicios/sunat/RepositorioDetalleOficialSol.php';
                if(!(new RepositorioDetalleOficialSol($this->conexion))->disponible())throw new DomainException('Importa primero la migración SQL del contenido oficial del Buzón SOL.');
                (new ServicioColaBuzonSol())->solicitar((int)$d['id_cliente'],(int)$_SESSION['id_usuario'],$id,false,'DETALLE');
                $mensaje='Consulta del contenido oficial programada. Podrás verlo al finalizar el proceso.';
            }elseif($accion==='adjuntar_pdf'){
                $referencia=(new ServicioArchivosPrivados())->guardarSubida($archivos['documento']??[],$buzon,(int)$d['id_cliente'],$id);
                $repo->vincular($buzon,$id,$referencia,$filtro);$mensaje='PDF vinculado a la notificación. Puedes visualizarlo, imprimirlo y descargarlo.';
            }elseif($accion==='cerrar_caso'||$accion==='reabrir_caso'){
                $repo->estado($buzon,$id,$accion==='cerrar_caso'?'REVISADO':'NO_REVISADO',$filtro);$mensaje=$accion==='cerrar_caso'?'Caso marcado como revisado en ASESCON.':'Caso reabierto en ASESCON.';
            }elseif($accion==='enviar_correo'){
                $destinatario=trim((string)($p['destinatario']??''));$texto=trim((string)($p['mensaje']??''));
                if(!filter_var($destinatario,FILTER_VALIDATE_EMAIL)||$texto===''||strlen($texto)>20000)throw new DomainException('Revisa el destinatario y el mensaje del correo.');
                $adjunto=(new ServicioArchivosPrivados())->resolver((string)($d['archivo']??''),$buzon);
                (new ServicioCorreoBuzonSol())->enviar($destinatario,'Notificación '.$buzon.' — '.preg_replace('/[\r\n]/',' ',(string)$d['ruc']),$texto,$d,$adjunto);
                $mensaje='El servidor SMTP aceptó el correo'.($adjunto?' con el PDF adjunto.':' con el resumen de la notificación.');
            }else throw new DomainException('Acción no válida.');
            $_SESSION['mensaje_documento_buzon']=$mensaje;
        }catch(Throwable $e){error_log('Documento de buzón: '.get_class($e));$_SESSION['error_documento_buzon']=$e instanceof DomainException?$e->getMessage():'No se pudo completar la acción. Revisa la configuración de correo o los permisos de almacenamiento.';}
        asesconRedirigir($ruta,['id_cliente'=>(int)($d['id_cliente']??0),'ver_documento'=>$id]);
    }
}
if(realpath((string)($_SERVER['SCRIPT_FILENAME']??''))===__FILE__){asesconExigirLogin();(new ControladorDocumentoBuzon($conexion))->procesar($_POST,$_FILES);}
