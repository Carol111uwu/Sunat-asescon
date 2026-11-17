<div id="rvieDatosVista" data-sire-cliente="<?= (int)$idCliente ?>" data-sire-periodo="<?= $h($periodo) ?>">
      <div class="reference-status-strip" id="rvieEstadoGuardado" tabindex="-1">
        <div class="status-main">
          <?php $hayImportacion = in_array((string)($actual['estado'] ?? ''), ['PROCESADO','GUARDADO','ANUAL'], true); ?>
          <span class="reference-dot <?= $hayImportacion ? '' : 'warning' ?>"></span>
          <span><strong><?= $hayImportacion ? ((int)$actual['cantidad_registros'] === 0 ? ($esGlobal ? 'Los resultados guardados muestran 0 comprobantes' : 'Propuesta SUNAT: 0 comprobantes') : 'Resultados guardados') : 'Sin resultados para mostrar' ?> — <?= $h($periodoContexto) ?></strong>
            <?php if ($esAnual && !$esGlobal): ?><br><small>Meses con resultado: <?= (int)($actual['meses_importados'] ?? 0) ?> de <?= (int)$mesesEsperados ?>. <?= (int)($actual['meses_importados'] ?? 0) < (int)$mesesEsperados ? 'Quedan períodos por consultar o confirmar.' : 'Todos los meses transcurridos tienen resultado.' ?></small><?php endif; ?>
            <br><small>Período SIRE: <?= $h($periodo) ?></small>
            <?php if ($actual['fecha_actualizacion']): ?><br><small>Última consulta registrada: <?= $h(date('d/m/Y H:i', strtotime((string)$actual['fecha_actualizacion']))) ?>.</small><?php endif; ?>
          </span>
        </div>
        <span class="reference-badge"><?= $h($actual['estado']) ?></span>
      </div>

      <div class="reference-panel compact rvie-filtro-periodo">
        <div class="reference-toolbar-row" style="margin:0">
          <div><strong style="font-size:12px">Mostrar solo comprobantes emitidos en el período seleccionado</strong>
            <p class="reference-subtle"><strong><?= $h($periodoContexto) ?></strong>. Al encender el filtro, solo se muestran los comprobantes emitidos en ese mes o año; los de otras fechas y los que no tienen fecha se ocultan temporalmente. Afecta el resumen, métricas, detalle y Excel resumen/detalle; <strong>no borra registros</strong> y el Excel original sigue íntegro.</p>
            <p class="reference-subtle" role="status"><?= $excluirAnteriores ? 'Filtro activado · ' . (int)$comprobantesVisibles . ' visibles y ' . (int)$comprobantesExcluidos . ' ocultos.' : 'Filtro desactivado · ' . (int)$comprobantesVisibles . ' comprobantes visibles.' ?></p>
          </div>
          <label class="reference-switch" title="Mostrar solo fechas de emisión del período"><input type="checkbox" id="toggleExcluirAnteriores" aria-label="Filtrar comprobantes por fecha de emisión del período" <?= $excluirAnteriores ? 'checked' : '' ?>><span class="track"></span></label>
        </div>
      </div>

      <div class="reference-metrics rvie-metricas">
        <div class="reference-metric"><span class="label">Comprobantes</span><strong><?= (int)$metricas['cantidad_registros'] ?></strong></div>
        <div class="reference-metric"><span class="label">Base imponible</span><strong>S/ <?= PresentacionDatosSire::formatearMonto($metricas['base_imponible']) ?></strong></div>
        <div class="reference-metric info"><span class="label">IGV</span><strong>S/ <?= PresentacionDatosSire::formatearMonto($metricas['igv']) ?></strong></div>
        <div class="reference-metric"><span class="label">Total</span><strong>S/ <?= PresentacionDatosSire::formatearMonto($metricas['total']) ?></strong><small>Origen: SUNAT SIRE</small></div>
      </div>

      <?php require __DIR__ . '/empresas_guardadas_sire.php'; ?>
      <div data-tabs id="rvieResultados">
        <div class="reference-tabs">
          <button type="button" class="reference-tab <?= $abrirDetalle ? '' : 'active' ?>" data-tab-target="rvie-resumen">Resumen de CP</button>
          <button type="button" class="reference-tab <?= $abrirDetalle ? 'active' : '' ?>" data-tab-target="rvie-detalle">Detalle <span class="count"><?= $totalDetallesMostrados ?></span></button>
        </div>
        <div data-tab-panel="rvie-resumen" <?= $abrirDetalle ? 'hidden' : '' ?>>
          <section class="panel module-panel">
            <div class="reference-table-title" style="padding:0 18px"><div><h2>Resumen agrupado por tipo de documento</h2><p>Período: <?= $h($periodoEtiqueta) ?><?= $esGlobal ? ' · Todas las empresas' : '' ?></p></div>
              <div class="reference-table-actions"><span class="reference-badge"><?= count($tipos) ?> tipos</span><a class="reference-btn" data-rvie-excel="resumen" href="<?= $h($excelBase . '&modo=resumen') ?>">⇩ Excel resumen</a></div>
            </div>
            <div class="table-container"><table id="tablaRvieResumen"><thead><tr><th>Tipo de documento</th><th>Total documentos</th><th>Base imponible</th><th>IGV</th><th>Total</th></tr></thead><tbody>
              <?php if ($tipos === []): ?><tr><td colspan="5"><div class="reference-empty">Todavía no se guardaron comprobantes RVIE de este período.</div></td></tr>
              <?php else: foreach ($tipos as $tipo): ?>
              <tr class="sire-summary-row" tabindex="0" role="button" data-sire-tipo="<?= $h($tipo['tipo']) ?>" data-open-sire-detail="rvie-detalle" <?= $esGlobal ? 'data-global="1"' : '' ?>>
                <td><?= $h($tipo['tipo']) ?></td><td><?= (int)$tipo['documentos'] ?></td><td>S/ <?= PresentacionDatosSire::formatearMonto($tipo['base']) ?></td><td>S/ <?= PresentacionDatosSire::formatearMonto($tipo['igv']) ?></td><td>S/ <?= PresentacionDatosSire::formatearMonto($tipo['total']) ?></td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody></table></div>
          </section>
        </div>
        <div data-tab-panel="rvie-detalle" <?= $abrirDetalle ? '' : 'hidden' ?>>
          <?php foreach (['mensaje_archivo_comprobante', 'error_archivo_comprobante'] as $claveAviso): ?>
            <?php if (isset($_SESSION[$claveAviso])): ?>
              <p role="<?= $claveAviso === 'error_archivo_comprobante' ? 'alert' : 'status' ?>"><?= $h($_SESSION[$claveAviso]) ?></p>
              <?php unset($_SESSION[$claveAviso]); ?>
            <?php endif; ?>
          <?php endforeach; ?>
          <section class="panel module-panel">
            <div class="reference-table-title" style="padding:0 18px"><div>
              <h2>Detalle de comprobantes RVIE</h2>
              <p><?= $esGlobal ? 'Una página completa por empresa. Siguiente muestra la próxima empresa, sin dividir sus comprobantes.' : 'Comprobantes importados del archivo SIRE del período.' ?></p>
              <?php if ($esGlobal && $clientePagina !== null): ?>
                <p><strong>Empresa <?= $pagina ?> de <?= $totalPaginas ?>: <?= $h($clientePagina['razon_social']) ?></strong> · RUC <?= $h($clientePagina['ruc']) ?></p>
              <?php endif; ?>
              <?php if ($esGlobal && $tipoFiltrado !== ''): ?><a class="reference-btn" href="<?= $h($urlDetalle(1)) ?>">Quitar filtro: <?= $h($tipoFiltrado) ?></a><?php endif; ?>
              <button type="button" class="reference-btn sire-clear-filter" hidden>Ver todos</button>
            </div><div class="reference-table-actions"><span class="reference-badge"><?= $esGlobal ? count($detallesVisibles) : $totalDetallesMostrados ?> comprobantes<?= $esGlobal ? ' de esta empresa' : '' ?></span>
              <a class="reference-btn" data-rvie-excel="detalle" href="<?= $h($excelBase . '&modo=detalle') ?>">⇩ Excel detalle</a>
              <?php if (!$esAnual && !$esGlobal): ?><a class="reference-btn" data-rvie-excel="original" href="<?= $h($excelBase . '&modo=original') ?>">Excel original</a><?php endif; ?>
            </div></div>
            <?php require __DIR__ . '/descarga_periodo_sire.php'; ?>
            <div class="sire-descarga-masiva" data-sire-masiva="rvie">
              <label for="rvieCantidadSeleccion" class="sire-masiva-cantidad">Seleccionar cantidad
                <select id="rvieCantidadSeleccion" data-sire-cantidad>
                  <option value="">Elegir cantidad</option>
                  <option value="10">10</option><option value="20">20</option>
                  <option value="50">50</option><option value="100">100</option>
                  <option value="todos">Todos los visibles</option>
                </select>
              </label>
              <span data-sire-contador role="status" aria-live="polite">0 seleccionados</span>
              <button type="button" class="reference-btn primary" data-sire-descargar disabled>Descargar seleccionados (0)</button>
              <button type="button" class="reference-btn" data-sire-limpiar disabled>Limpiar selección</button>
              <small class="sire-masiva-nota">Cada PDF se descarga por separado, sin ZIP. El navegador puede solicitar permiso para descargas múltiples.</small>
              <div class="sire-masiva-progreso" data-sire-progreso role="status" aria-live="polite"></div>
            </div>
            <div class="table-container"><table><thead><tr>
              <th class="sire-columna-seleccion"><input type="checkbox" data-sire-todos aria-label="Seleccionar todos los comprobantes visibles"></th>
              <?php if ($esGlobal): ?><th>Empresa ASESCON</th><?php endif; ?>
              <th>Fecha de emisión</th><th>Tipo</th><th>Serie</th><th>Número</th><th>Documento cliente</th><th>Cliente</th><th>Base imponible</th><th>IGV</th><th>Total</th><th>Estado</th><th>Acciones</th>
            </tr></thead><tbody>
              <?php if ($detallesVisibles === []): ?><tr><td colspan="<?= $esGlobal ? 13 : 12 ?>"><div class="reference-empty">No hay comprobantes visibles para este filtro.</div></td></tr>
              <?php else: foreach ($detallesVisibles as $detalle): ?>
                <tr class="sire-detail-row" data-sire-tipo="<?= $h($detalle['tipo_comprobante'] ?? '—') ?>">
                  <td class="sire-columna-seleccion"><input type="checkbox" class="sire-seleccion-fila" aria-label="Seleccionar comprobante <?= $h(($detalle['serie'] ?? '') . '-' . ($detalle['numero'] ?? '')) ?>"></td>
                  <?php if ($esGlobal): ?><td><strong><?= $h($detalle['empresa_asescon'] ?? '—') ?></strong><br><small><?= $h($detalle['ruc_empresa'] ?? '') ?></small></td><?php endif; ?>
                  <td><?= !empty($detalle['fecha_emision']) ? $h(date('d/m/Y', strtotime((string)$detalle['fecha_emision']))) : '—' ?></td>
                  <td><?= $h($detalle['tipo_comprobante'] ?? '—') ?></td><td><?= $h($detalle['serie'] ?? '—') ?></td><td><?= $h($detalle['numero'] ?? '—') ?></td>
                  <td><?= $h($detalle['documento_cliente'] ?? '—') ?></td><td><?= $h($detalle['razon_social_cliente'] ?? '—') ?></td>
                  <td>S/ <?= PresentacionDatosSire::formatearMonto($detalle['base_imponible'] ?? 0) ?></td>
                  <td>S/ <?= PresentacionDatosSire::formatearMonto($detalle['igv'] ?? 0) ?></td>
                  <td>S/ <?= PresentacionDatosSire::formatearMonto($detalle['importe_total'] ?? 0) ?></td>
                  <td><?= $h($detalle['estado_registro'] ?? '—') ?></td>
                  <?php
                    $clienteFila = $esGlobal ? (int)($detalle['id_cliente'] ?? 0) : $idCliente;
                    $comprobanteUrl = $apiComprobante . '?' . http_build_query([
                        'cliente' => $clienteFila,
                        'detalle' => (int)$detalle['id_detalle'],
                    ]);
                    $rucEmisorFila = $detalle['ruc_empresa'] ?? $rucEmpresaPorCliente[$clienteFila] ?? '';
                    $numeroFila = trim(($detalle['serie'] ?? '') . '-' . ($detalle['numero'] ?? ''), '-');
                  ?>
                  <?php require __DIR__ . '/botones_comprobante_sire.php'; ?>
                </tr>
              <?php endforeach; endif; ?>
            </tbody></table></div>
            <?php if ($esGlobal && $clientePagina !== null): ?>
              <nav class="rvie-paginacion" aria-label="Páginas por empresa">
                <?php if ($pagina > 1): ?><a class="reference-btn" href="<?= $h($urlDetalle($pagina - 1, $tipoFiltrado)) ?>">Anterior empresa</a><?php endif; ?>
                <span>Empresa <?= $pagina ?> de <?= $totalPaginas ?> · <?= $h($clientePagina['razon_social']) ?></span>
                <?php if ($pagina < $totalPaginas): ?><a class="reference-btn" href="<?= $h($urlDetalle($pagina + 1, $tipoFiltrado)) ?>">Siguiente empresa</a><?php endif; ?>
              </nav>
            <?php endif; ?>
          </section>
        </div>
      </div>
      <?php require __DIR__ . '/dialogos_comprobante_sire.php'; ?>
      <?php $moduloArchivos = 'RVIE'; require __DIR__ . '/archivo_comprobante.php'; ?>
</div>
