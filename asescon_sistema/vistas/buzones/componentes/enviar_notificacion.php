<details class="buzon-enviar">
    <summary class="reference-btn" title="Elegir cómo enviar esta notificación">Enviar</summary>
    <div class="buzon-canales">
        <?php if ($buzonCodigo === 'SUNAFIL' && $puedeConsultar): ?>
            <a class="buzon-icono" href="caso_sunafil.php?id_documento=<?= $idDocumento ?>#correo-sunafil"
               <?= etiquetaAccionBuzon('Enviar por correo') ?>><?= iconoAccionBuzonSol('correo') ?></a>
        <?php else: ?>
            <button type="button" class="buzon-icono" data-buzon-detalle="<?= $buzonCodigo ?>"
                    data-accion-inicial="correo" data-id-documento="<?= $idDocumento ?>"
                    <?= etiquetaAccionBuzon('Preparar correo electrónico') ?>><?= iconoAccionBuzonSol('correo') ?></button>
        <?php endif; ?>
        <button type="button" class="buzon-icono" data-buzon-detalle="<?= $buzonCodigo ?>"
                data-accion-inicial="whatsapp" data-id-documento="<?= $idDocumento ?>"
                <?= etiquetaAccionBuzon('Preparar WhatsApp al número del cliente') ?>><?= iconoAccionBuzonSol('whatsapp') ?></button>
    </div>
</details>
