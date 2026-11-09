<?php
declare(strict_types=1);
require_once __DIR__.'/../../../modelos/sunat/declaraciones/RepositorioDeclaracionesOficiales.php';
$repositorioOficial=new RepositorioDeclaracionesOficiales($conexion);
$clientesOficiales=$repositorioOficial->clientes(asesconFiltroClientesSql($conexion,'c'));
$puedeConsultarOficial=asesconTienePermiso($conexion,'DECLARACIONES','ejecutar');
$csrfOficial=asesconCsrf('csrf_consulta_oficial');
?>
<section class="reference-panel consulta-oficial" id="consultaOficial" data-api="<?php echo htmlspecialchars(asesconUrl('api/sunat/consultar_declaraciones.php')); ?>" data-csrf="<?php echo htmlspecialchars($csrfOficial); ?>" data-ejecutar="<?php echo $puedeConsultarOficial?'1':'0'; ?>">
    <details>
        <summary><strong>Historial oficial de declaraciones y pagos SUNAT</strong></summary>
        <p>Consulta por empresa y fecha de presentación. Incluye formularios, constancias, casillas y anexos disponibles.</p>
        <?php if(!$repositorioOficial->disponible()): ?>
            <p role="status">El historial oficial todavía no está preparado en este servidor. La persona encargada de la instalación debe completar la actualización del sistema.</p>
        <?php else: ?>
        <form class="reference-filter-grid" id="formConsultaOficial">
            <label class="reference-field"><span>Empresa</span><select name="id_cliente" required><option value="">Selecciona una empresa</option><?php foreach($clientesOficiales as $c): ?><option value="<?php echo (int)$c['id_cliente']; ?>" data-ruc="<?php echo htmlspecialchars($c['ruc']); ?>" data-correo="<?php echo htmlspecialchars((string)$c['correo']); ?>"><?php echo htmlspecialchars($c['ruc'].' — '.$c['razon_social']); ?></option><?php endforeach; ?></select></label>
            <label class="reference-field"><span>Presentadas desde</span><input type="date" name="inicio" value="<?php echo date('Y-m-01'); ?>" required></label>
            <label class="reference-field"><span>Hasta</span><input type="date" name="fin" value="<?php echo date('Y-m-d'); ?>" required></label>
            <div class="reference-actions"><button class="reference-btn primary" <?php echo $puedeConsultarOficial?'':'disabled'; ?>>Consultar SUNAT</button></div>
        </form>
        <p id="estadoConsultaOficial" role="status" aria-live="polite"></p>
        <div class="reference-actions" id="exportacionesOficiales" hidden><button type="button" class="reference-btn" data-reporte="pdf">PDF oficial</button><button type="button" class="reference-btn" data-reporte="xls">Excel oficial</button></div>
        <div class="table-container"><table id="tablaOficial" hidden><thead><tr><th>Período</th><th>Formulario</th><th>Descripción</th><th>N.º orden</th><th>Presentación</th><th>Banco</th><th>Pago informado</th><th>Documentos</th></tr></thead><tbody></tbody></table></div>
        <dialog id="detalleOficial" class="dialogo-oficial" aria-labelledby="tituloDetalleOficial">
            <div class="cabecera-oficial"><h2 id="tituloDetalleOficial">Detalle oficial</h2><button type="button" class="reference-btn" data-cerrar-dialogo aria-label="Cerrar detalle">×</button></div>
            <p id="estadoDetalleOficial" role="status" aria-live="polite"></p><div id="contenidoDetalleOficial"></div>
            <div class="reference-actions" id="accionesDetalleOficial"></div>
            <iframe id="pdfOficial" title="Documento PDF oficial de SUNAT" hidden></iframe>
            <form id="formCorreoConstancia" class="reference-actions" hidden><label class="reference-field"><span>Enviar constancia oficial a</span><input type="email" name="destinatario" autocomplete="email" required></label><button class="reference-btn">Enviar por correo</button></form>
            <p class="reference-subtle">Consultar este historial no presenta declaraciones ni realiza pagos. El detalle corresponde a la constancia consultada; no certifica una conciliación bancaria posterior.</p>
        </dialog>
        <?php endif; ?>
    </details>
</section>
