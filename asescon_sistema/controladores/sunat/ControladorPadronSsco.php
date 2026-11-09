<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../modelos/comunicaciones/ConfiguracionComunicaciones.php';
require_once __DIR__.'/../../modelos/sunat/PadronSsco.php';
require_once __DIR__.'/../../servicios/sunat/LectorPadronSsco.php';

final class ControladorPadronSsco
{
    public function __construct(private mysqli $conexion) {}
    public function procesar(array $p, array $archivos): void
    {
        asesconExigirPermiso($this->conexion,'ALERTAS_SUNAT','editar');
        if ($_SERVER['REQUEST_METHOD']!=='POST' || !asesconValidarCsrf((string)($p['csrf_token']??''),'csrf_ssco')) { http_response_code(403);exit('Solicitud no válida.'); }
        try {
            if (!(new ConfiguracionComunicaciones($this->conexion))->disponible()) { throw new DomainException('La cuenta principal debe preparar el módulo de configuración.'); }
            $lector=new LectorPadronSsco();
            if (($p['accion']??'')==='actualizar') { $registros=$lector->descargar();$origen='SUNAT'; }
            elseif (($p['accion']??'')==='importar') {
                $a=$archivos['padron']??[];
                $extension=strtolower(pathinfo((string)($a['name']??''),PATHINFO_EXTENSION));
                if (($a['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_uploaded_file((string)($a['tmp_name']??'')) || !in_array($extension,['csv','xlsx'],true)) {
                    throw new DomainException('Selecciona el XLSX oficial o un CSV válido.');
                }
                $registros=$extension==='xlsx'?$lector->desdeXlsx($a['tmp_name']):$lector->desdeCsv($a['tmp_name']);$origen=strtoupper($extension).' validado';
            } else { throw new DomainException('Acción no válida.'); }
            $n=(new PadronSsco($this->conexion))->guardar($registros,$origen);
            $_SESSION['mensaje_ssco']=$n.' registros procesados sin duplicar RUC y resolución.';
        } catch (Throwable $e) {
            error_log('Padrón SSCO: '.get_class($e));
            $_SESSION['error_ssco']=$e instanceof DomainException ? $e->getMessage() : 'No se pudo guardar el padrón. Se conserva la información anterior.';
        }
        asesconRedirigir('vistas/alertas/alertas_sunat.php');
    }
}
if (realpath((string)($_SERVER['SCRIPT_FILENAME']??''))===__FILE__) { (new ControladorPadronSsco($conexion))->procesar($_POST,$_FILES); }
