<?php

declare(strict_types=1);
if (!isset($seccionClientes, $conexion)) { http_response_code(404); exit; }

require_once __DIR__.'/../../../configuracion/inicializacion.php';
require_once __DIR__.'/../ControladorWhatsApp.php';
asesconExigirPermiso($conexion,'WHATSAPP','ver');$datos=(new ControladorWhatsApp($conexion))->datos();extract($datos);$csrf=asesconCsrf('csrf_whatsapp');
$tab=(string)($_GET['tab']??'recordatorios');if(!in_array($tab,['recordatorios','plantillas','historial','archivados'],true))$tab='recordatorios';
$categorias=['VENCIMIENTOS'=>'Vencimientos','PENDIENTES'=>'Pendientes','DIAS_FESTIVOS'=>'Días festivos','DIA_TRABAJADOR'=>'Día del trabajador','RECORDATORIO_VENCIMIENTO'=>'Recordatorio de vencimiento','SALUDO_NAVIDAD'=>'Saludo de Navidad','NAVIDAD'=>'Navidad','TIP_SUNAT'=>'Tip SUNAT'];

