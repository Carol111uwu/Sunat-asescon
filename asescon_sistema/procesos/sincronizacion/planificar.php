<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
date_default_timezone_set('America/Lima');
require_once dirname(__DIR__,2).'/servicios/sincronizacion/PlanificadorSincronizacion.php';
if(!ColaNocturna::enHorario())exit;
require_once dirname(__DIR__,2).'/configuracion/conexion.php';
$resumen=(new PlanificadorSincronizacion($conexion))->programar();
echo 'Sincronizacion nocturna: '.$resumen['clientes'].' clientes, '.$resumen['trabajos'].' trabajos nuevos, periodo SIRE: '.implode(', ',$resumen['periodos_sire']).'.'.PHP_EOL;
