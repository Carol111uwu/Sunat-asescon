<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../modelos/sunat/ArchivoComprobante.php';
require_once __DIR__.'/../../modelos/sunat/ComprobanteSunat.php';
require_once __DIR__.'/../../servicios/archivos/ServicioArchivosPrivados.php';
require_once __DIR__.'/../../servicios/archivos/GeneradorContenedorZip.php';
require_once __DIR__.'/../../servicios/sunat/LectorListaComprobantes.php';

final class ControladorArchivoComprobante
{
    public function __construct(private mysqli $conexion){}
    public function procesar(array $p,array $archivos):void
    {
        asesconExigirLogin();$modulo=(string)($p['modulo']??'COMPROBANTES_SUNAT');if(!in_array($modulo,['COMPROBANTES_SUNAT','RCE'],true)){http_response_code(400);exit('Módulo no válido.');}
        asesconExigirPermiso($this->conexion,$modulo,($p['accion']??'')==='descarga_masiva'?'ejecutar':'editar');
        if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||!asesconValidarCsrf((string)($p['csrf_token']??''),'csrf_archivos_comprobantes')){http_response_code(403);exit('Solicitud no válida.');}
        $cliente=(int)($p['id_cliente']??0);if(!asesconPuedeVerCliente($this->conexion,$cliente)){http_response_code(403);exit('Cliente no autorizado.');}
        try{
            $repo=new ArchivoComprobante($this->conexion);$tipo=strtoupper((string)($p['tipo_archivo']??'PDF'));$col=match($tipo){'PDF'=>'archivo_pdf','XML'=>'archivo_xml','CDR'=>'archivo_cdr',default=>throw new DomainException('Tipo no válido.')};$servicio=new ServicioArchivosPrivados();
            if(($p['accion']??'')==='descarga_masiva'){
                $a=$archivos['lista']??[];if(($a['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file((string)($a['tmp_name']??'')))throw new DomainException('Selecciona una lista CSV o XLSX.');
                $claves=(new LectorListaComprobantes())->leer($a['tmp_name'],strtolower(pathinfo((string)$a['name'],PATHINFO_EXTENSION)),$cliente);$entradas=[];$tamanoLote=0;$limiteLote=50*1024*1024;
                $memoria=trim((string)ini_get('memory_limit'));if($memoria!==''&&$memoria!=='-1'){$valor=(float)$memoria;$unidad=strtoupper(substr($memoria,-1));$bytesMemoria=(int)($valor*match($unidad){'G'=>1073741824,'M'=>1048576,'K'=>1024,default=>1});$limiteLote=min($limiteLote,max(0,(int)(($bytesMemoria-memory_get_usage(true)-8*1024*1024)/4)));}
                $resumen="ruc_emisor,tipo,serie,numero,resultado\r\n";
                foreach($claves as $k){$d=$repo->obtener($k);$ref=(string)($d[$col]??'');if($ref==='')$ref=(string)((new ComprobanteSunat($this->conexion))->buscarArchivo($cliente,$k['ruc_emisor'],$k['tipo_documento'],$k['serie'],$k['numero'],$tipo)['ruta']??'');$ruta=$servicio->resolver($ref,'COMPROBANTES');$resultado=$ruta?'INCLUIDO':'SIN_ARCHIVO_VINCULADO';$resumen.=implode(',',[$k['ruc_emisor'],$k['tipo_documento'],$k['serie'],$k['numero'],$resultado])."\r\n";if($ruta){$tamanoLote+=(int)filesize($ruta);if($tamanoLote>$limiteLote)throw new DomainException('El lote supera el tamaño disponible para esta descarga. Reduce la lista y procesa los documentos en grupos.');$nombre=$k['ruc_emisor'].'_'.$k['tipo_documento'].'_'.$k['serie'].'_'.$k['numero'].'.'.($tipo==='CDR'?'zip':strtolower($tipo));$entradas[$nombre]=(string)file_get_contents($ruta);}}
                $entradas['resultado.csv']=$resumen;$bytes=(new GeneradorContenedorZip())->crear($entradas);header('Content-Type: application/zip');header('Content-Disposition: attachment; filename="comprobantes_'.strtolower($tipo).'.zip"');header('Content-Length: '.strlen($bytes));header('Cache-Control: private, no-store');echo $bytes;exit;
            }
            if(!$repo->disponible())throw new DomainException('Prepara los módulos complementarios desde Configuración de correo.');
            if(($p['accion']??'')==='agregar_cuenta'){$repo->agregarCuenta($cliente,trim((string)($p['codigo']??'')),trim((string)($p['nombre']??'')));$_SESSION['mensaje_archivo_comprobante']='Cuenta guardada sin duplicar su código.';}
            else{
                $k=ClaveComprobante::normalizar($p);
                if(!(new ComprobanteSunat($this->conexion))->existeParaCliente($k))throw new DomainException('El comprobante no está registrado para este cliente.');
                $cuenta=trim((string)($p['cuenta_contable']??''));$centro=trim((string)($p['centro_costos']??''));if(strlen($cuenta)>30||preg_match_all('/./us',$centro)>120)throw new DomainException('Cuenta o centro de costos demasiado extensos.');
                if($cuenta!==''&&!in_array($cuenta,array_column($repo->cuentas($cliente),'codigo'),true))throw new DomainException('Agrega o selecciona una cuenta del catálogo de este cliente.');
                $anterior=$repo->obtener($k);$ref=(string)($anterior[$col]??'');
                if(($archivos['documento']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){$ident=(int)hexdec(substr(hash('sha256',ClaveComprobante::identificador($k)),0,8));$ref=$servicio->guardarSubida($archivos['documento'],'COMPROBANTES',$cliente,max(1,$ident),$tipo);}
                $repo->guardar($k,$tipo,$ref,$cuenta,$centro);$_SESSION['mensaje_archivo_comprobante']='Archivo y clasificación guardados para el comprobante.';
            }
        }catch(Throwable $e){error_log('Archivo de comprobante: '.get_class($e));$_SESSION['error_archivo_comprobante']=$e instanceof DomainException?$e->getMessage():'No se pudo procesar el archivo.';}
        asesconRedirigir($modulo==='RCE'?'vistas/sunat/rce.php':'vistas/sunat/comprobantes_sunat.php',$modulo==='RCE'?['cliente'=>$cliente,'periodo'=>(string)($p['periodo']??date('Ym'))]:['cliente_desc'=>$cliente,'tab'=>'descripcion']);
    }
}
if(realpath((string)($_SERVER['SCRIPT_FILENAME']??''))===__FILE__)(new ControladorArchivoComprobante($conexion))->procesar($_POST,$_FILES);
