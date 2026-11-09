<section class="reference-panel consulta-oficial" id="consultaOficial" data-api="<?php echo htmlspecialchars(asesconUrl('api/sunat/declaraciones_pagos/consultar_declaraciones_y_pagos.php')); ?>" data-csrf="<?php echo htmlspecialchars($csrfOficial); ?>" data-ejecutar="<?php echo $puedeConsultarOficial?'1':'0'; ?>" data-seccion="<?php echo htmlspecialchars($seccionOficial); ?>">
    <p>Selecciona una empresa y las fechas de presentación para consultar su información oficial.</p>
    <?php if(!$historialOficialDisponible): ?>
        <div class="notice bad" role="status"><strong>Consulta oficial no disponible en esta instalación.</strong> Faltan las tablas de almacenamiento para las consultas y declaraciones oficiales en la base principal. No equivale a que la empresa no tenga declaraciones o pagos. La migración pendiente es <code>2026_10_15_consultas_oficiales_declaraciones.sql</code> y debe revisarse con un respaldo antes de aplicarla sobre la base vigente. Mientras tanto puedes utilizar Control de declaraciones para los registros internos.</div>
    <?php else: ?>
        <?php require __DIR__.'/navegacion_consultas.php'; ?>
        <?php require __DIR__.'/formulario_consulta.php'; ?>
        <p id="estadoConsultaOficial" role="status" aria-live="polite"></p>
        <section id="vistaOficial_consulta" data-panel-oficial="consulta">
            <h2 class="titulo-seccion-oficial">Consulta de declaraciones y pagos</h2>
            <?php require __DIR__.'/resultados_consulta.php'; ?>
        </section>
        <section id="vistaOficial_constancias" data-panel-oficial="constancias" hidden>
            <?php require __DIR__.'/constancias.php'; ?>
        </section>
        <section id="vistaOficial_casillas" data-panel-oficial="casillas" hidden>
            <?php require __DIR__.'/casillas_y_anexos.php'; ?>
        </section>
        <section id="vistaOficial_reportes" data-panel-oficial="reportes" hidden>
            <?php require __DIR__.'/reportes.php'; ?>
        </section>
        <?php require __DIR__.'/ventana_documento.php'; ?>
    <?php endif; ?>
</section>
