        <dialog id="detalleOficial" class="dialogo-oficial" aria-labelledby="tituloDetalleOficial">
            <div class="cabecera-oficial"><h2 id="tituloDetalleOficial">Documento y detalle oficial</h2><button type="button" class="reference-btn" data-cerrar-dialogo aria-label="Cerrar detalle">×</button></div>
            <p id="estadoDetalleOficial" role="status" aria-live="polite"></p><div id="contenidoDetalleOficial"></div>
            <div class="reference-actions" id="accionesDetalleOficial"></div>
            <iframe id="pdfOficial" title="Documento PDF oficial de SUNAT" hidden></iframe>
            <form id="formCorreoConstancia" class="reference-actions" hidden><label class="reference-field"><span data-correo-documento>Enviar constancia oficial a</span><input type="email" name="destinatario" autocomplete="email" required></label><button class="reference-btn">Enviar por correo</button></form>
            <p class="reference-subtle">Consultar este historial no presenta declaraciones ni realiza pagos. El detalle corresponde a la constancia consultada; no certifica una conciliación bancaria posterior.</p>
        </dialog>
