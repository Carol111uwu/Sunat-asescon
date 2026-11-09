<?php if (!isset($conexion,$preferenciasCorreo)) { http_response_code(404);exit; } ?>
<section class="reference-panel"><h2>Correos y plantillas</h2>
<form class="comunicaciones-formulario" method="post" action="<?=$escCorreo($accionCorreo)?>">
<input type="hidden" name="csrf_token" value="<?=$escCorreo($csrfCorreo)?>">
<label class="reference-field">Nombre del remitente<input name="nombre_remitente" maxlength="120" required value="<?=$escCorreo($preferenciasCorreo['nombre_remitente'])?>"></label>
<label class="reference-field">Correo de copia (CC)<input name="correo_copia" type="email" maxlength="254" value="<?=$escCorreo($preferenciasCorreo['correo_copia'])?>"><small>Recibirá copia de los entregables enviados a clientes.</small></label>
<label class="reference-field ancho-completo">Asunto del correo<input name="plantilla_asunto" maxlength="200" required value="<?=$escCorreo($preferenciasCorreo['plantilla_asunto'])?>"></label>
<label class="reference-field ancho-completo">Mensaje del correo<textarea name="plantilla_mensaje" maxlength="10000" required><?=$escCorreo($preferenciasCorreo['plantilla_mensaje'])?></textarea></label>
<div class="comunicaciones-variables ancho-completo"><span><code>{cliente}</code> Nombre del cliente</span><span><code>{ruc}</code> RUC</span><span><code>{entregables}</code> Lista seleccionada</span></div>
<label class="reference-field">Correo para avisos de SUNAT y SUNAFIL<input name="correo_avisos" type="email" maxlength="254" value="<?=$escCorreo($preferenciasCorreo['correo_avisos'])?>"><small>Correo interno del estudio que recibirá las notificaciones nuevas.</small></label>
<label class="comunicaciones-check"><input type="checkbox" name="avisos_activos" value="1" <?=!empty($preferenciasCorreo['avisos_activos'])?'checked':''?>>Activar avisos automáticos por correo</label>
<p class="reference-subtle ancho-completo">Al activar los avisos, se notifican los documentos nuevos que se sincronicen desde ese momento. El correo incluye un enlace al buzón de ASESCON que exige iniciar sesión.</p>
<div class="reference-actions ancho-completo"><button class="reference-btn primary" name="accion" value="guardar">Guardar configuración</button></div></form></section>
