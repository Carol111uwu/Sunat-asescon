<?php if(!isset($conexion,$csrf)){http_response_code(404);exit;}$escConst=static fn($v):string=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');?>
<dialog class="visor-documento" id="constanciaDeclaracionModal"><header class="visor-documento-cabecera"><h2 id="constanciaDeclaracionTitulo">Constancia de declaración</h2><button type="button" data-constancia-cerrar aria-label="Cerrar constancia">×</button></header>
<p>Vincula el PDF descargado del portal oficial y registra los datos revisados. Esta operación no consulta ni presenta una declaración en SUNAT.</p>
<form method="post" enctype="multipart/form-data" action="<?=$escConst(asesconUrl('controladores/sunat/ControladorConstanciaDeclaracion.php'))?>" id="formularioConstanciaDeclaracion">
<input type="hidden" name="csrf_token" value="<?=$escConst($csrf)?>"><input type="hidden" name="id">
<label class="reference-field">Constancia PDF (máximo 15 MB)<input name="constancia" type="file" accept=".pdf,application/pdf"></label>
<label class="reference-field">Número de orden<input name="numero_orden" maxlength="50"></label><label class="reference-field">Fecha de presentación<input name="fecha_presentacion" type="datetime-local"></label>
<label class="reference-field">Monto declarado<input name="monto_declarado" type="number" min="0" step="0.01" required></label>
<label class="reference-field">Estado<select name="estado_presentacion"><option value="PENDIENTE">Pendiente</option><option value="PRESENTADA">Presentada</option></select></label>
<label class="comunicaciones-check"><input type="checkbox" name="confirmar_constancia" value="1">Revisé la constancia oficial y sus datos antes de marcarla como presentada.</label>
<button class="reference-btn primary">Guardar constancia y datos</button></form></dialog>
<link rel="stylesheet" href="<?=$escConst(asesconUrl('publico/css/modulos/visor_documentos.css'))?>?v=20261014_4"><script defer src="<?=$escConst(asesconUrl('publico/js/sunat/constancia_declaracion.js'))?>?v=20261014_4"></script>
