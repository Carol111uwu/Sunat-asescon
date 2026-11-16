<?php
$libroZipSire = strtolower($moduloActivo);
?>
<div class="sire-descarga-masiva" data-sire-periodo-zip="<?= $h($libroZipSire) ?>"
     data-sire-periodo="<?= $h($periodo) ?>" data-sire-cliente="<?= (int)$idCliente ?>"
     data-sire-excluir="<?= $excluirAnteriores ? '1' : '0' ?>" data-sire-tipo="<?= $h($tipoFiltrado) ?>"
     data-sire-zip-url="<?= $h(asesconUrl('api/sunat/descarga_masiva_sire_zip.php')) ?>">
  <button type="button" class="reference-btn" data-sire-descargar-periodo>Descargar todo el período (ZIP)</button>
  <button type="button" class="reference-btn" data-sire-detener-periodo hidden>Detener</button>
  <small>Hasta 100 reportes PDF por ZIP. Incluye los registros consultados del período.</small>
  <div class="sire-masiva-progreso" data-sire-progreso role="status" aria-live="polite"></div>
</div>
