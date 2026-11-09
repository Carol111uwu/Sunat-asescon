<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../modelos/comunicaciones/ConfiguracionComunicaciones.php';
require_once __DIR__.'/../../modelos/comunicaciones/EnvioEntregables.php';
asesconExigirPermiso($conexion,'INFORMES','ver');$moduloActivo='ENVIO_ENTREGABLES';
$configuracionEntrega=new ConfiguracionComunicaciones($conexion);$entregaDisponible=$configuracionEntrega->disponible();
$escEntrega=static fn($v):string=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$csrfEntrega=asesconCsrf('csrf_entregables');$claveEntrega=bin2hex(random_bytes(32));
$_SESSION['claves_entregables']=array_slice($_SESSION['claves_entregables']??[],-9,null,true);$_SESSION['claves_entregables'][$claveEntrega]=true;
$clientesEntrega=[];$tiposEntrega=[];$historialEntrega=[];
if($entregaDisponible){
    $filtroEntrega=asesconFiltroClientesSql($conexion,'c');
    $clientesEntrega=$conexion->query("SELECT c.id_cliente,c.ruc,c.razon_social,c.correo FROM clientes c WHERE {$filtroEntrega} AND c.estado='ACTIVO' ORDER BY c.razon_social")->fetch_all(MYSQLI_ASSOC);
    $tiposEntrega=$configuracionEntrega->entregables();$historialEntrega=(new EnvioEntregables($conexion))->historial($filtroEntrega);
}
$mensajeEntrega=$_SESSION['mensaje_entregables']??'';$errorEntrega=$_SESSION['error_entregables']??'';unset($_SESSION['mensaje_entregables'],$_SESSION['error_entregables']);
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Envío de entregables - ASESCON</title><link rel="stylesheet" href="../../publico/css/base/estructura_panel.css?v=20261028_ui2"><link rel="stylesheet" href="../../publico/css/modulos/sunat.css"></head><body><div class="app">
<?php require __DIR__.'/../componentes/menu_lateral.php';?><main class="main-content"><?php require __DIR__.'/../componentes/barra_superior.php';?><section class="content comunicaciones-contenido">
<div class="page-header"><div><h1>Envío de entregables</h1><p>Envía documentos al correo registrado del cliente y consulta el historial.</p></div></div>
<?php if($mensajeEntrega):?><p class="comunicaciones-aviso" role="status"><?=$escEntrega($mensajeEntrega)?></p><?php endif;?>
<?php if($errorEntrega):?><p class="comunicaciones-aviso error" role="alert"><?=$escEntrega($errorEntrega)?></p><?php endif;?>
<?php if(!$entregaDisponible):?><section class="reference-panel"><p>La cuenta principal debe preparar la configuración de correo para usar este módulo.</p><?php if(asesconEsPrincipal($conexion)):?><a class="reference-btn" href="configuracion_correo.php">Abrir configuración</a><?php endif;?></section>
<?php else:?>
<?php if(asesconTienePermiso($conexion,'INFORMES','ejecutar')):?>
<section class="reference-panel"><h2>Nuevo envío</h2>
<?php if(!$tiposEntrega):?><p>Primero agrega tipos de entregable en la configuración de correo.</p><?php elseif(!$clientesEntrega):?><p>No hay clientes activos autorizados para tu cuenta.</p><?php else:?>
<form class="comunicaciones-formulario" method="post" enctype="multipart/form-data" action="../../controladores/comunicaciones/ControladorEnvioEntregables.php">
<input type="hidden" name="csrf_token" value="<?=$escEntrega($csrfEntrega)?>"><input type="hidden" name="clave_envio" value="<?=$escEntrega($claveEntrega)?>">
<label class="reference-field ancho-completo">Cliente y destinatario<select name="id_cliente" required><option value="">Seleccionar cliente</option><?php foreach($clientesEntrega as $clienteEntrega):?><option value="<?=(int)$clienteEntrega['id_cliente']?>" <?=(int)($_GET['id_cliente']??0)===(int)$clienteEntrega['id_cliente']?'selected':''?>><?=$escEntrega($clienteEntrega['razon_social'].' · '.$clienteEntrega['ruc'].' · '.($clienteEntrega['correo']?:'Sin correo registrado'))?></option><?php endforeach;?></select></label>
<fieldset class="ancho-completo"><legend>Entregables incluidos</legend><?php foreach($tiposEntrega as $tipoEntrega):?><label class="comunicaciones-check"><input type="checkbox" name="entregables[]" value="<?=(int)$tipoEntrega['id_entregable']?>"><?=$escEntrega($tipoEntrega['nombre'])?></label><?php endforeach;?></fieldset>
<label class="reference-field ancho-completo">Archivos adjuntos<input type="file" name="adjuntos[]" multiple required accept=".pdf,.csv,.xml,.xlsx,.zip,.png,.jpg,.jpeg"><small>Hasta cinco archivos y 10 MB en total. El servidor puede aplicar un límite de carga menor.</small></label>
<p class="reference-subtle ancho-completo">El botón enviará el correo con los archivos seleccionados y la plantilla guardada. Si hay correo de copia configurado, también recibirá el envío.</p>
<div class="reference-actions ancho-completo"><button class="reference-btn primary">Enviar correo con adjuntos</button></div></form><?php endif;?></section>
<?php endif;?>
<section class="reference-panel"><h2>Últimos envíos</h2><div class="table-container"><table><thead><tr><th>Cliente</th><th>Destinatario</th><th>Asunto</th><th>Fecha</th><th>Estado</th></tr></thead><tbody>
<?php if(!$historialEntrega):?><tr><td colspan="5">No hay envíos registrados para tus clientes.</td></tr><?php endif;?>
<?php foreach($historialEntrega as $envioEntrega):?><tr><td><?=$escEntrega($envioEntrega['razon_social'])?><small><?=$escEntrega($envioEntrega['ruc'])?></small></td><td><?=$escEntrega($envioEntrega['destinatario'])?></td><td><?=$escEntrega($envioEntrega['asunto'])?></td><td><?=$escEntrega($envioEntrega['creado_en'])?></td><td><?=$escEntrega($envioEntrega['estado'])?></td></tr><?php endforeach;?></tbody></table></div></section><?php endif;?>
<footer>© <?= (int)(new DateTimeImmutable("now", new DateTimeZone("America/Lima")))->format("Y") ?> ASESCON</footer></section></main></div><script src="../../publico/js/navegacion/menu_lateral.js"></script><script src="../../publico/js/navegacion/busqueda_clientes.js?v=20261014_1"></script><script src="../../publico/js/navegacion/menu_movil.js"></script><script src="../../publico/js/interfaz/tablas_adaptables.js"></script></body></html>
