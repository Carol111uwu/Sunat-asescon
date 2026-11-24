<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
date_default_timezone_set('America/Lima');
require_once dirname(__DIR__,2).'/servicios/sincronizacion/PlanificadorSincronizacion.php';
if ($argc>1) {
    if ($argc!==5 || $argv[1]!=='--reintentar-sire' || !ctype_digit($argv[2])) {
        fwrite(STDERR,"Uso: planificar.php --reintentar-sire ID_CLIENTE RVIE|RCE AAAAMM".PHP_EOL);
        exit(2);
    }
    require_once dirname(__DIR__,2).'/configuracion/conexion.php';
    try {
        $programado=(new PlanificadorSincronizacion($conexion))->reintentarSire((int)$argv[2],$argv[3],$argv[4]);
        echo $programado
            ? 'Período puesto en cola. El cron lo retomará en su próxima ronda.'.PHP_EOL
            : 'No se agregó otro trabajo. El período ya está confirmado o no tiene un trabajo fallido que reintentar hoy.'.PHP_EOL;
    } catch (DomainException $error) {
        fwrite(STDERR,$error->getMessage().PHP_EOL);
        exit(2);
    }
    exit;
}

$ahora=new DateTimeImmutable();
if((int)$ahora->format('H')<2)exit;
$cola=new ColaNocturna();
if($cola->estaPausada())exit;
require_once dirname(__DIR__,2).'/configuracion/conexion.php';
$resumen=(new PlanificadorSincronizacion($conexion,$cola))->programar(soloHistorico:!ColaNocturna::enHorario($ahora));
if($resumen['trabajos']>0){
    echo 'Sincronización automática: '.$resumen['clientes'].' clientes, '.$resumen['trabajos']
        .' trabajos nuevos, '.$resumen['historicos'].' períodos históricos de SIRE.'.PHP_EOL;
}
