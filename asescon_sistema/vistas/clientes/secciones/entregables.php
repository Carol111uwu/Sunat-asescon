<?php if (!isset($seccionClientes, $conexion)) { http_response_code(404); exit; } ?>

<div class="page-header"><div><h2>Envío de entregables</h2><p>Envía documentos al correo registrado del cliente y consulta el historial.</p></div></div>
<?php if($mensajeEntrega):?><p class="comunicaciones-aviso" role="status"><?=$escEntrega($mensajeEntrega)?></p><?php endif;?>
<?php if($errorEntrega):?><p class="comunicaciones-aviso error" role="alert"><?=$escEntrega($errorEntrega)?></p><?php endif;?>
<?php if(!$entregaDisponible):?><section class="reference-panel"><p>La cuenta principal debe preparar la configuración de correo para usar este módulo.</p><?php if(asesconEsPrincipal($conexion)):?><a class="reference-btn" href="<?=htmlspecialchars(asesconUrl('vistas/comunicaciones/configuracion_correo.php'),ENT_QUOTES,'UTF-8')?>">Abrir configuración</a><?php endif;?></section>
<?php else:?>
<?php if(asesconTienePermiso($conexion,'INFORMES','ejecutar')):?>
<section class="reference-panel"><h2>Nuevo envío</h2>
<?php if(!$tiposEntrega):?><p>Primero agrega tipos de entregable en la configuración de correo.</p><?php elseif(!$clientesEntrega):?><p>No hay clientes activos autorizados para tu cuenta.</p><?php else:?>
<form class="comunicaciones-formulario" method="post" enctype="multipart/form-data" action="<?=htmlspecialchars(asesconUrl('controladores/comunicaciones/ControladorEnvioEntregables.php'),ENT_QUOTES,'UTF-8')?>">
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

