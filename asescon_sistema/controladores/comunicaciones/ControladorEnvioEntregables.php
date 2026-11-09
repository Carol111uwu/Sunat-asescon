<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../modelos/comunicaciones/ConfiguracionComunicaciones.php';
require_once __DIR__.'/../../modelos/comunicaciones/EnvioEntregables.php';
require_once __DIR__.'/../../servicios/comunicaciones/PlantillaCorreo.php';
require_once __DIR__.'/../../servicios/comunicaciones/AdjuntosEntregables.php';
require_once __DIR__.'/../../servicios/comunicaciones/ServicioCorreoSmtp.php';

final class ControladorEnvioEntregables
{
    public function __construct(private mysqli $conexion) {}
    public function procesar(array $p, array $archivos): void
    {
        asesconExigirPermiso($this->conexion,'INFORMES','ejecutar');
        if ($_SERVER['REQUEST_METHOD']!=='POST' || !asesconValidarCsrf((string)($p['csrf_token']??''),'csrf_entregables')) { http_response_code(403);exit('Solicitud no válida.'); }
        $idEnvio=null;$modelo=new EnvioEntregables($this->conexion);
        try {
            $configuracion=new ConfiguracionComunicaciones($this->conexion);
            if (!$configuracion->disponible()) { throw new DomainException('La cuenta principal debe preparar la configuración de correo.'); }
            $idCliente=(int)($p['id_cliente']??0);
            if (!asesconPuedeVerCliente($this->conexion,$idCliente)) { throw new DomainException('El cliente no está autorizado para tu cuenta.'); }
            $clave=(string)($p['clave_envio']??'');
            if (!preg_match('/^[a-f0-9]{64}$/D',$clave) || !isset($_SESSION['claves_entregables'][$clave])) { throw new DomainException('Abre de nuevo el formulario para enviar.'); }
            $s=$this->conexion->prepare("SELECT razon_social,ruc,correo FROM clientes WHERE id_cliente=? AND estado='ACTIVO'");
            $s->bind_param('i',$idCliente);$s->execute();$cliente=$s->get_result()->fetch_assoc();$s->close();
            if (!$cliente || !filter_var($cliente['correo'],FILTER_VALIDATE_EMAIL)) { throw new DomainException('Registra un correo válido en la ficha del cliente antes de enviar.'); }
            $ids=array_values(array_unique(array_map('intval',is_array($p['entregables']??null)?$p['entregables']:[])));
            $catalogo=$configuracion->entregables();$nombres=[];
            foreach ($catalogo as $tipo) { if (in_array((int)$tipo['id_entregable'],$ids,true)) { $nombres[]=$tipo['nombre']; } }
            if (count($nombres)!==count($ids)) { throw new DomainException('La selección de entregables cambió; vuelve a abrir el formulario.'); }
            $config=$configuracion->obtener();$contenido=PlantillaCorreo::preparar($config,$cliente,$nombres);
            if (preg_match_all('/./us',$contenido['asunto'])>200) { throw new DomainException('El asunto con el nombre del cliente supera los 200 caracteres. Reduce la plantilla del asunto.'); }
            $adjuntos=AdjuntosEntregables::validar($archivos['adjuntos']??[]);
            $idEnvio=$modelo->reservar($clave,$idCliente,(int)$_SESSION['id_usuario'],$cliente['correo'],$contenido['asunto']);
            unset($_SESSION['claves_entregables'][$clave]);
            if ($idEnvio===null) { throw new DomainException('Ese envío ya fue registrado. Consulta el historial antes de volver a enviarlo.'); }
            (new ServicioCorreoSmtp())->enviar($cliente['correo'],$contenido['asunto'],$contenido['mensaje'],$config['nombre_remitente'],$config['correo_copia'],$adjuntos);
            $modelo->terminar($idEnvio,true);
            $_SESSION['mensaje_entregables']='El servidor de correo aceptó el envío a '.$cliente['correo'].'.';
        } catch (Throwable $e) {
            if ($idEnvio!==null) { $modelo->terminar($idEnvio,false); }
            error_log('Envío de entregables: '.get_class($e));
            $_SESSION['error_entregables']=$e instanceof DomainException ? $e->getMessage() : 'El envío no se completó. Revisa el correo SMTP y el historial antes de volver a enviarlo.';
        }
        asesconRedirigir('vistas/clientes/comunicacion_informes.php', ['seccion' => 'entregables']);
    }
}
if (realpath((string)($_SERVER['SCRIPT_FILENAME']??''))===__FILE__) { (new ControladorEnvioEntregables($conexion))->procesar($_POST,$_FILES); }
