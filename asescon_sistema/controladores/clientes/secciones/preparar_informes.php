<?php

declare(strict_types=1);
if (!isset($seccionClientes, $conexion)) { http_response_code(404); exit; }

require_once __DIR__.'/../../../configuracion/inicializacion.php';
require_once __DIR__.'/../ControladorInformes.php';
asesconExigirPermiso($conexion,'INFORMES','ver');

$filtros=[
 'id_cliente'=>(int)($_GET['id_cliente']??0),
 'fecha_inicio'=>trim((string)($_GET['fecha_inicio']??'')),
 'fecha_fin'=>trim((string)($_GET['fecha_fin']??'')),
 'responsable'=>trim((string)($_GET['responsable']??'')),
 'firma'=>trim((string)($_GET['firma']??'ESTUDIO_CONTABLE')),
 'q'=>trim((string)($_GET['q']??'')),
];
$datos=(new ControladorInformes($conexion))->datos($filtros);extract($datos);
$actorNombre=(string)($actor['usuario']??'Usuario actual');foreach($responsables as $rr){if((int)$rr['id_usuario']===(int)$actor['id_usuario']){$actorNombre=(string)$rr['nombre_mostrar'];break;}}
$buscando=$filtros['id_cliente']>0||$filtros['fecha_inicio']!==''||$filtros['fecha_fin']!==''||$filtros['responsable']!==''||isset($_GET['buscar']);

