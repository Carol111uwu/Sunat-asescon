<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../modelos/comunicaciones/ConfiguracionComunicaciones.php';
require_once __DIR__.'/../../modelos/comunicaciones/ColaAvisosBuzones.php';
asesconExigirLogin();
if (!asesconEsPrincipal($conexion)) { http_response_code(403);exit('Solo la cuenta principal puede configurar los correos.'); }
$moduloActivo='CONFIGURACION_CORREO';
$modeloCorreo=new ConfiguracionComunicaciones($conexion);
$correoDisponible=$modeloCorreo->disponible();
$preferenciasCorreo=$correoDisponible?$modeloCorreo->obtener():[];
$tiposEntregable=$correoDisponible?$modeloCorreo->entregables():[];
$resumenAvisos=$correoDisponible?(new ColaAvisosBuzones($conexion))->resumen():[];
$csrfCorreo=asesconCsrf('csrf_comunicaciones');
$escCorreo=static fn($v):string=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$accionCorreo=asesconUrl('controladores/comunicaciones/ControladorConfiguracionCorreo.php');
$mensajeCorreo=$_SESSION['mensaje_comunicaciones']??'';$errorCorreo=$_SESSION['error_comunicaciones']??'';
unset($_SESSION['mensaje_comunicaciones'],$_SESSION['error_comunicaciones']);
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Configuración de correo - ASESCON</title>
<link rel="stylesheet" href="../../publico/css/base/estructura_panel.css?v=20261028_ui2"><link rel="stylesheet" href="../../publico/css/modulos/sunat.css"></head><body><div class="app">
<?php require __DIR__.'/../componentes/menu_lateral.php';?><main class="main-content"><?php require __DIR__.'/../componentes/barra_superior.php';?><section class="content comunicaciones-contenido">
<div class="page-header"><div><h1>Configuración de correo</h1><p>Remitente, plantillas, avisos de buzones y tipos de entregable del estudio.</p></div></div>
<?php if($mensajeCorreo):?><p class="comunicaciones-aviso" role="status"><?=$escCorreo($mensajeCorreo)?></p><?php endif;?>
<?php if($errorCorreo):?><p class="comunicaciones-aviso error" role="alert"><?=$escCorreo($errorCorreo)?></p><?php endif;?>
<?php if(!$correoDisponible):?>
<section class="reference-panel"><h2>Preparar el módulo</h2><p>Crea las tablas de configuración, entregables, avisos y padrón SSCO. Conserva los clientes, usuarios y documentos existentes.</p>
<form method="post" action="<?=$escCorreo($accionCorreo)?>"><input type="hidden" name="csrf_token" value="<?=$escCorreo($csrfCorreo)?>"><button class="reference-btn primary" name="accion" value="preparar">Preparar configuración</button></form></section>
<?php else:?>
<?php require __DIR__.'/componentes/formulario_configuracion.php';?>
<?php require __DIR__.'/componentes/catalogo_entregables.php';?>
<?php require __DIR__.'/componentes/vista_previa_correo.php';?>
<section class="reference-panel"><h2>Preparar módulos complementarios</h2><p>Crea las tablas de archivos de comprobantes y cuentas contables sin reemplazar registros existentes.</p><form method="post" action="<?=$escCorreo($accionCorreo)?>"><input type="hidden" name="csrf_token" value="<?=$escCorreo($csrfCorreo)?>"><button class="reference-btn" name="accion" value="preparar">Preparar / actualizar módulos</button></form></section>
<section class="reference-panel"><h2>Comprobar el envío</h2><p>Envía un mensaje de prueba a tu propio correo para comprobar que el servidor puede enviar. Esta prueba no selecciona clientes ni adjunta entregables.</p><form class="comunicaciones-formulario" method="post" action="<?=$escCorreo($accionCorreo)?>">
<input type="hidden" name="csrf_token" value="<?=$escCorreo($csrfCorreo)?>"><label class="reference-field">Tu correo para comprobar el SMTP<input type="email" name="correo_prueba" maxlength="254" required autocomplete="email"></label><div class="reference-actions"><button class="reference-btn" name="accion" value="prueba">Enviar correo de prueba</button></div></form></section>
<section class="reference-panel"><h2>Estado de los avisos</h2><p>Se procesan con los cron existentes de SUNAT y SUNAFIL. Un error de sincronización del buzón no se resuelve con esta configuración.</p><div class="comunicaciones-variables">
<?php if(!$resumenAvisos):?><span>No se han registrado avisos nuevos.</span><?php endif;?>
<?php foreach($resumenAvisos as $resumen):?><span><?=$escCorreo($resumen['estado'])?>: <strong><?=(int)$resumen['total']?></strong></span><?php endforeach;?></div>
<form method="post" action="<?=$escCorreo($accionCorreo)?>"><input type="hidden" name="csrf_token" value="<?=$escCorreo($csrfCorreo)?>"><button class="reference-btn" name="accion" value="reintentar">Reintentar avisos con error</button></form></section>
<?php endif;?>
<footer>© <?= (int)(new DateTimeImmutable("now", new DateTimeZone("America/Lima")))->format("Y") ?> ASESCON</footer></section></main></div>
<script src="../../publico/js/navegacion/menu_lateral.js"></script><script src="../../publico/js/navegacion/busqueda_clientes.js?v=20261014_1"></script><script src="../../publico/js/navegacion/menu_movil.js"></script></body></html>
