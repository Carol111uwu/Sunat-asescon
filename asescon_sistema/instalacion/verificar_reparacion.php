<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') {http_response_code(404);exit;}
require_once dirname(__DIR__).'/configuracion/conexion.php';
require_once dirname(__DIR__).'/servicios/sunat/CargadorCredencialesSunat.php';
date_default_timezone_set('America/Lima');
$todoBien=true;
$q=$conexion->query('SELECT id_cliente,ruc FROM clientes WHERE estado=\'ACTIVO\' ORDER BY id_cliente LIMIT 200');
$cantidad=0;
while($row=$q->fetch_assoc()) {
    $id=(int)$row['id_cliente'];$cantidad++;
    try {
        $loader=new CargadorCredencialesSunat($conexion);
        $sol=$loader->obtenerSolPorCliente($id);
        $sire=$loader->obtenerPorRuc((string)$row['ruc']);
        $ok=trim((string)($sol['clave_sol']??''))!=='' && trim((string)($sire['client_secret_sunat']??''))!=='';
        if(!$ok)throw new RuntimeException('VACIO');
        echo 'CLIENTE_'.$id.'_CIFRADO_OK'.PHP_EOL;
    } catch(Throwable $e) {
        $todoBien=false;
                                                                                         
        echo 'CLIENTE_'.$id.'_CIFRADO_ERROR_'.get_class($e).PHP_EOL;
    }
}
echo 'CLIENTES_REVISADOS_'.$cantidad.PHP_EOL;
echo 'PRUEBA_LOCAL_'.($todoBien&&$cantidad>0?'OK':'ERROR').PHP_EOL;
exit($todoBien&&$cantidad>0?0:2);
