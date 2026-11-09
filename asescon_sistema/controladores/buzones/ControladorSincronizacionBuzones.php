<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../servicios/sunat/ServicioColaBuzonSol.php';
require_once __DIR__.'/../../servicios/sunafil/ServicioColaBuzonSunafil.php';
asesconExigirLogin();
if (!asesconEsPrincipal($conexion) || !asesconTienePermiso($conexion,'BUZON_SOL','editar')) {
    http_response_code(403); exit('No tienes permiso para sincronizar todos los buzones.');
}
if (($_SERVER['REQUEST_METHOD']??'')!=='POST') {http_response_code(405);exit('Método no permitido.');}
if (!asesconValidarCsrf((string)($_POST['csrf_token']??''),'csrf_buzones_global')) {
    http_response_code(403); exit('Sesión vencida, recarga la página.');
}
$ruta=asesconRutaAlmacenamiento().'/sincronizacion/solicitudes_buzones.lock';
$resultado=['tipo'=>'error','texto'=>'No se pudo programar la consulta general.'];
try {
    if (!is_dir(dirname($ruta))&&!mkdir(dirname($ruta),0700,true)&&!is_dir(dirname($ruta))) throw new RuntimeException('Sin almacenamiento.');
    $f=fopen($ruta,'c+');
    if (!$f||!flock($f,LOCK_EX)) throw new RuntimeException('Solicitud ocupada.');
    try {
        $ultima=(int)trim((string)stream_get_contents($f));
        if ($ultima>time()-600) throw new DomainException('Ya se solicitó una revisión general. Espera diez minutos.');
        $consulta=$conexion->query("SELECT DISTINCT c.id_cliente FROM clientes c INNER JOIN credenciales_sunat cs ON cs.id_cliente=c.id_cliente WHERE c.estado='ACTIVO' AND cs.estado=1 AND TRIM(COALESCE(cs.usuario_sol,''))<>'' AND TRIM(COALESCE(cs.clave_sol_cifrada,''))<>'' ORDER BY c.id_cliente");
        $sol=new ServicioColaBuzonSol();$sunafil=new ServicioColaBuzonSunafil();
        $clientes=0;$nuevos=0;$omitidos=0;
        while ($c=$consulta->fetch_assoc()) {
            $id=(int)$c['id_cliente'];$clientes++;
            foreach ([$sol,$sunafil] as $cola) {
                try {$r=$cola->solicitar($id,0); if(empty($r['reutilizada'])) $nuevos++;}
                catch(Throwable $e){$omitidos++;error_log('Solicitud buzones empresa '.$id.': '.get_class($e));}
            }
        }
        rewind($f);ftruncate($f,0);fwrite($f,(string)time());fflush($f);
        $resultado=['tipo'=>'ok','texto'=>'Revisión solicitada para '.$clientes.' empresas: '.$nuevos.' tareas nuevas y '.$omitidos.' no programadas. Cron seguirá trabajando en segundo plano.'];
    } finally {flock($f,LOCK_UN);fclose($f);}
} catch(DomainException $e) {$resultado=['tipo'=>'aviso','texto'=>$e->getMessage()];}
catch(Throwable $e) {error_log('Buzones globales '.get_class($e));}
$_SESSION['mensaje_buzones_global']=$resultado;
asesconRedirigir('vistas/buzones/buzon_sol_sunat.php');
