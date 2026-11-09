<?php
declare(strict_types=1);
require_once __DIR__.'/../../modelos/comunicaciones/ColaAvisosBuzones.php';
require_once __DIR__.'/ServicioCorreoSmtp.php';

final class ProcesadorAvisosBuzones
{
    public function __construct(private mysqli $conexion) {}

    public function procesar(): void
    {
        $modelo=new ConfiguracionComunicaciones($this->conexion);
        if (!$modelo->disponible()) { return; }
                                                                                             
        $bloqueo=$this->conexion->query("SELECT GET_LOCK(CONCAT(DATABASE(),':avisos'),0) obtenido")->fetch_assoc();
        if (!(int)$bloqueo['obtenido']) { return; }
        try {
            $config=$modelo->obtener();
            if (!(int)$config['avisos_activos']) { return; }
            $cola=new ColaAvisosBuzones($this->conexion);
                                                                                               
            $cola->registrar('SUNAT');$cola->registrar('SUNAFIL');
            $smtp=require __DIR__.'/../../configuracion/correo.php';
            $base=rtrim((string)$smtp['base_url'],'/');
            if (!filter_var($base,FILTER_VALIDATE_URL) || parse_url($base,PHP_URL_SCHEME)!=='https') {
                throw new RuntimeException('Configura la URL pública HTTPS para los avisos.');
            }
            foreach ($cola->pendientes() as $aviso) {
                $id=(int)$aviso['id_aviso'];
                $intentos=$cola->registrarIntento($id);
                try {
                    $documento=$cola->documento($aviso);
                    if (!$documento) { $cola->estado($id,'ANULADO');continue; }
                    $ruta=$aviso['entidad']==='SUNAT' ? 'buzon_sol_sunat.php' : 'buzon_sunafil.php';
                    $mensaje="Nueva notificación de {$aviso['entidad']}\nCliente: {$documento['razon_social']}\nRUC: {$documento['ruc']}\nAsunto: {$documento['asunto']}\n\nRevisa el documento iniciando sesión en ASESCON:\n{$base}/vistas/buzones/{$ruta}";
                    (new ServicioCorreoSmtp())->enviar($config['correo_avisos'],'Nueva notificación '.$aviso['entidad'].' — '.$documento['ruc'],$mensaje,$config['nombre_remitente']);
                    $cola->estado($id,'ENVIADO');
                } catch (Throwable $e) {
                    if ($intentos>=5) { $cola->estado($id,'ERROR'); }
                    error_log('Aviso de buzón '.$id.': envío no completado ('.get_class($e).').');
                                                                                                  
                    break;
                }
            }
        } finally { $this->conexion->query("SELECT RELEASE_LOCK(CONCAT(DATABASE(),':avisos'))"); }
    }
}
