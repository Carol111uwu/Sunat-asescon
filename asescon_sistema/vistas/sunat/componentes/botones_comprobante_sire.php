<td><div class="rce-fila-acciones" data-sire-pdf-url="<?= $h($comprobanteUrl) ?>">
  <a class="rce-icono-accion" href="<?= $h($comprobanteUrl . '&vista=previa') ?>" target="_blank" rel="noopener" title="Ver PDF" aria-label="Ver PDF"><?= $iconoRce('ver') ?></a>
  <a class="rce-icono-accion" href="<?= $h($comprobanteUrl) ?>" title="Descargar PDF" aria-label="Descargar PDF"><?= $iconoRce('pdf') ?></a>
  <a class="rce-icono-accion" href="<?= $h($comprobanteUrl . '&formato=xlsx') ?>" title="Descargar Excel" aria-label="Descargar Excel"><?= $iconoRce('excel') ?></a>
  <?php if ($puedeEnviarCorreo): ?>
    <button type="button" class="rce-icono-accion" data-rce-correo data-rce-url="<?= $h($comprobanteUrl) ?>" data-rce-numero="<?= $h($numeroFila) ?>" title="Enviar PDF por correo" aria-label="Enviar PDF por correo"><?= $iconoRce('correo') ?></button>
  <?php endif; ?>
  <button type="button" class="rce-icono-accion" data-rce-whatsapp data-rce-url="<?= $h($comprobanteUrl) ?>" data-rce-numero="<?= $h($numeroFila) ?>" title="Compartir PDF por WhatsApp" aria-label="Compartir PDF por WhatsApp"><?= $iconoRce('whatsapp') ?></button>
  <button type="button" class="rce-icono-accion" title="Archivos / cuenta" aria-label="Archivos / cuenta" data-archivo-comprobante="<?= $h(json_encode([
    'id_cliente' => $clienteFila,
    'ruc_emisor' => $rucEmisorFila,
    'tipo_documento' => $detalle['tipo_comprobante'] ?? '',
    'serie' => $detalle['serie'] ?? '',
    'numero' => $detalle['numero'] ?? '',
    'periodo' => $detalle['periodo'] ?? $periodo,
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"><?= $iconoRce('archivos') ?></button>
</div></td>
