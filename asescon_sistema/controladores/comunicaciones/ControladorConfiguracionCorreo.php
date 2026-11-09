<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../modelos/comunicaciones/ConfiguracionComunicaciones.php';
require_once __DIR__.'/../../modelos/comunicaciones/ColaAvisosBuzones.php';
require_once __DIR__.'/../../servicios/comunicaciones/PlantillaCorreo.php';
require_once __DIR__.'/../../servicios/comunicaciones/ServicioCorreoSmtp.php';

final class ControladorConfiguracionCorreo
{
    public function __construct(private mysqli $conexion) {}

    public function procesar(array $p): void
    {
        asesconExigirLogin();
        if (!asesconEsPrincipal($this->conexion)) { http_response_code(403);exit('Solo la cuenta principal puede configurar los correos.'); }
        if ($_SERVER['REQUEST_METHOD']!=='POST' || !asesconValidarCsrf((string)($p['csrf_token']??''),'csrf_comunicaciones')) { http_response_code(403);exit('Solicitud no válida.'); }
        $modelo=new ConfiguracionComunicaciones($this->conexion);
        try {
            $accion=(string)($p['accion']??'');
            if ($accion==='preparar') { $modelo->instalar(); }
            else {
                if (!$modelo->disponible()) { throw new DomainException('Primero prepara el módulo.'); }
                switch ($accion) {
                    case 'guardar':
                        $datos=PlantillaCorreo::validarConfiguracion($p);
                        if ($datos['avisos_activos']) {
                            $smtp=require __DIR__.'/../../configuracion/correo.php';
                            if (!filter_var($smtp['base_url'],FILTER_VALIDATE_URL) || parse_url($smtp['base_url'],PHP_URL_SCHEME)!=='https') {
                                throw new DomainException('Configura la URL pública HTTPS en los datos privados SMTP antes de activar avisos.');
                            }
                        }
                        $modelo->guardar($datos);break;
                    case 'agregar': $modelo->agregarEntregable(PlantillaCorreo::nombreEntregable((string)($p['nombre']??'')));break;
                    case 'eliminar': $modelo->eliminarEntregable((int)($p['id_entregable']??0));break;
                    case 'predeterminados':
                        foreach (['Liquidación de impuestos','Constancia de declaración','Registro de compras','Registro de ventas','Planilla de remuneraciones','Informe contable'] as $nombre) { $modelo->agregarEntregable($nombre); }break;
                    case 'prueba':
                        $config=$modelo->obtener();$destino=trim((string)($p['correo_prueba']??''));
                        (new ServicioCorreoSmtp())->enviar($destino,'Prueba de correo ASESCON','La configuración SMTP de ASESCON permite enviar correos.',$config['nombre_remitente']);break;
                    case 'reintentar': (new ColaAvisosBuzones($this->conexion))->reintentarErrores();break;
                    default: throw new DomainException('Acción no válida.');
                }
            }
            $_SESSION['mensaje_comunicaciones']='Cambios aplicados. Si enviaste una prueba, revisa también la carpeta de correo no deseado.';
        } catch (Throwable $e) {
            error_log('Configuración de correo: '.get_class($e));
            $_SESSION['error_comunicaciones']=$e instanceof DomainException ? $e->getMessage() : 'No se pudo completar la acción. Revisa la configuración privada SMTP y los permisos de la base de datos.';
        }
        asesconRedirigir('vistas/comunicaciones/configuracion_correo.php');
    }
}
if (realpath((string)($_SERVER['SCRIPT_FILENAME']??''))===__FILE__) {
    (new ControladorConfiguracionCorreo($conexion))->procesar($_POST);
}
