<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
date_default_timezone_set('America/Lima');
require_once dirname(__DIR__,2).'/servicios/sincronizacion/PlanificadorSincronizacion.php';
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
