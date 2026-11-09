<?php if(!isset($buzonCodigo,$csrf)){http_response_code(404);exit;} ?>
<section id="visorDocumentoSeguimiento" hidden>
<h3>Notas y seguimiento</h3><p>Comentarios internos de ASESCON. Se guardan aparte del documento de SUNAT y no cambian con la sincronización.</p>
<div id="visorDocumentoNotas" class="buzon-notas-lista" aria-live="polite"></div>
<form id="visorDocumentoFormNota" action="<?=$escDocumento(asesconUrl('api/buzones/notas_documento.php'))?>" method="post" data-visor-editar data-visor-cliente hidden>
<input type="hidden" name="buzon" value="<?=$escDocumento($buzonCodigo)?>"><input type="hidden" name="csrf_token" value="<?=$escDocumento($csrf)?>"><input type="hidden" name="id_documento" data-visor-id><input type="hidden" name="clave_nota">
<label class="reference-field">Nueva nota<textarea name="nota" rows="3" required maxlength="5000" placeholder="Ej.: Pagado, pendiente de revisión o indicaciones para el equipo."></textarea></label>
<button class="reference-btn primary" type="submit">Guardar nota</button><p id="visorDocumentoNotaEstado" role="status"></p>
</form></section>
