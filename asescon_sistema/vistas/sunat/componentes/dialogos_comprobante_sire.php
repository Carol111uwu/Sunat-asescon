<?php if ($puedeEnviarCorreo): ?>
<dialog id="rceCorreo" class="rce-dialogo" aria-labelledby="rceCorreoTitulo">
  <form id="rceCorreoFormulario" method="post" action="<?= $h($apiComprobante) ?>">
    <h2 id="rceCorreoTitulo">Enviar PDF por correo</h2>
    <p data-rce-documento></p>
    <input type="hidden" name="csrf_token" value="<?= $h(asesconCsrf($csrfCorreo)) ?>">
    <input type="hidden" name="cliente">
    <input type="hidden" name="detalle">
    <label for="rceCorreoDestino">Correo del destinatario</label>
    <input id="rceCorreoDestino" type="email" name="destinatario" required maxlength="254" autocomplete="email">
    <p data-rce-estado role="status" aria-live="polite"></p>
    <div class="rce-dialogo-acciones">
      <button type="button" class="reference-btn" data-rce-cerrar>Cerrar</button>
      <button type="submit" class="reference-btn primary">Enviar PDF</button>
    </div>
  </form>
</dialog>
<?php endif; ?>
<dialog id="rceWhatsApp" class="rce-dialogo" aria-labelledby="rceWhatsAppTitulo">
  <h2 id="rceWhatsAppTitulo">Compartir por WhatsApp</h2>
  <p data-rce-documento></p>
  <p>En computadora, descarga el PDF, abre WhatsApp y adjúntalo al chat.</p>
  <p data-rce-estado role="status" aria-live="polite"></p>
  <div class="rce-dialogo-acciones">
    <button type="button" class="reference-btn" data-rce-cerrar>Cerrar</button>
    <button type="button" class="reference-btn" data-rce-compartir hidden>Compartir PDF</button>
    <a class="reference-btn" data-rce-descargar>Descargar PDF</a>
    <a class="reference-btn primary" href="https://web.whatsapp.com/" target="_blank" rel="noopener noreferrer">Abrir WhatsApp</a>
  </div>
</dialog>
