<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
date_default_timezone_set('America/Lima');
try{
    if(PHP_VERSION_ID<80200)throw new RuntimeException('PHP_INCOMPATIBLE');
    foreach(['mysqli','curl','openssl','mbstring','dom','zip'] as $extension){
        if(!extension_loaded($extension)){echo 'FALTA_EXTENSION_'.$extension.PHP_EOL;exit(2);}
    }
    echo 'PHP_EXTENSIONES_OK'.PHP_EOL;
    require_once dirname(__DIR__).'/configuracion/conexiones_bases_datos.php';
    $db=asesconConexionPrincipal();$db->query('SELECT 1');
    asesconConexionHistoricoSunat()->query('SELECT 1');
    echo 'DOS_CONEXIONES_MYSQL_OK'.PHP_EOL;
    require_once dirname(__DIR__).'/configuracion/rutas_privadas.php';
    $base=asesconRutaAlmacenamiento();
    if(!is_dir($base)||!is_writable($base))throw new RuntimeException('ALMACENAMIENTO_NO_DISPONIBLE');
    echo 'ALMACENAMIENTO_PRIVADO_OK'.PHP_EOL;
    require_once dirname(__DIR__).'/servicios/sincronizacion/ColaNocturna.php';
    require_once dirname(__DIR__).'/servicios/sincronizacion/ResumenSincronizacion.php';
    $clientes=[];$r=$db->query('SELECT id_cliente,ruc,razon_social FROM clientes ORDER BY id_cliente');
    while($c=$r->fetch_assoc())$clientes[]=$c;
    $resumen=ResumenSincronizacion::construir((new ColaNocturna())->resumen(),$clientes,ResumenSincronizacion::fechaConsulta(''));
    echo 'RESUMEN_LOCAL_OK'.PHP_EOL;
    $runtime=dirname(__DIR__,2).'/asescon_recursos_privados/RUNTIME_OK';
    echo (is_file($runtime)?'MARCA_RUNTIME_EXISTENTE_OK':'REVISAR_MARCA_RUNTIME_EXISTENTE').PHP_EOL;
    echo 'VERIFICACION_ACTUALIZACION_OK'.PHP_EOL;
    echo 'Este resultado valida archivos y conexiones locales. No prueba autenticacion ni PDF de SUNAT.'.PHP_EOL;
}catch(Throwable $e){
                                                                      
    echo 'VERIFICACION_ACTUALIZACION_ERROR_'.get_class($e).PHP_EOL;
    echo 'Revisa conexiones, extensiones y permisos de las carpetas privadas existentes.'.PHP_EOL;
    exit(2);
}
