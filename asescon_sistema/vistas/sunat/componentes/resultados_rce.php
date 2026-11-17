<div id="rceDatosVista" data-sire-cliente="<?= (int)$idCliente ?>" data-sire-periodo="<?= $h($periodo) ?>">
      <div class="reference-status-strip" id="rceEstadoGuardado" tabindex="-1">
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

      <div class="reference-panel compact rce-filtro-periodo">
        <div class="reference-toolbar-row" style="margin:0">
          <div><strong style="font-size:12px">Mostrar solo comprobantes emitidos en el período seleccionado</strong>
            <p class="reference-subtle"><strong><?= $h($periodoContexto) ?></strong>. Al encender el filtro, solo se muestran los comprobantes emitidos en ese mes o año; los de otras fechas y los que no tienen fecha se ocultan temporalmente. Afecta el resumen, métricas, detalle y Excel resumen/detalle; <strong>no borra registros</strong> y el Excel original sigue íntegro.</p>
            <p class="reference-subtle" role="status"><?= $excluirAnteriores ? 'Filtro activado · ' . (int)$comprobantesVisibles . ' visibles y ' . (int)$comprobantesExcluidos . ' ocultos.' : 'Filtro desactivado · ' . (int)$comprobantesVisibles . ' comprobantes visibles.' ?></p>
          </div>
          <label class="reference-switch" title="Mostrar solo fechas de emisión del período"><input type="checkbox" id="toggleExcluirAnteriores" aria-label="Filtrar comprobantes por fecha de emisión del período" <?= $excluirAnteriores ? 'checked' : '' ?>><span class="track"></span></label>
        </div>
      </div>

      <div class="reference-metrics rce-metricas">
        <div class="reference-metric"><span class="label">Comprobantes</span><strong><?= (int)$metricas['cantidad_registros'] ?></strong></div>
        <div class="reference-metric"><span class="label">Base imponible</span><strong>S/ <?= PresentacionDatosSire::formatearMonto($metricas['base_imponible']) ?></strong></div>
        <div class="reference-metric info"><span class="label">IGV</span><strong>S/ <?= PresentacionDatosSire::formatearMonto($metricas['igv']) ?></strong></div>
        <div class="reference-metric"><span class="label">Total</span><strong>S/ <?= PresentacionDatosSire::formatearMonto($metricas['total']) ?></strong><small>Origen: SUNAT SIRE</small></div>
      </div>

      <?php require __DIR__ . '/empresas_guardadas_sire.php'; ?>
      <div data-tabs id="rceResultados">
        <div class="reference-tabs">
          <button type="button" class="reference-tab <?= $abrirDetalle ? '' : 'active' ?>" data-tab-target="rce-resumen">Resumen de CP</button>
          <button type="button" class="reference-tab <?= $abrirDetalle ? 'active' : '' ?>" data-tab-target="rce-detalle">Detalle <span class="count"><?= $totalDetallesMostrados ?></span></button>
        </div>
        <div data-tab-panel="rce-resumen" <?= $abrirDetalle ? 'hidden' : '' ?>>
          <section class="panel module-panel">
            <div class="reference-table-title" style="padding:0 18px"><div><h2>Resumen agrupado por tipo de documento</h2><p>Período: <?= $h($periodoEtiqueta) ?><?= $esGlobal ? ' · Todas las empresas' : '' ?></p></div>
              <div class="reference-table-actions"><span class="reference-badge"><?= count($tipos) ?> tipos</span><a class="reference-btn" data-rce-excel="resumen" href="<?= $h($excelBase . '&modo=resumen') ?>">⇩ Excel resumen</a></div>
            </div>
            <div class="table-container"><table id="tablaRceResumen"><thead><tr><th>Tipo de documento</th><th>Total documentos</th><th>Base imponible</th><th>IGV</th><th>Total</th></tr></thead><tbody>
              <?php if ($tipos === []): ?><tr><td colspan="5"><div class="reference-empty">Pulsa Ver/consultar para obtener comprobantes RCE de este período.</div></td></tr>
              <?php else: foreach ($tipos as $tipo): ?>
              <tr class="sire-summary-row" tabindex="0" role="button" data-sire-tipo="<?= $h($tipo['tipo']) ?>" data-open-sire-detail="rce-detalle" <?= $esGlobal ? 'data-global="1"' : '' ?>>
                <td><?= $h($tipo['tipo']) ?></td><td><?= (int)$tipo['documentos'] ?></td><td>S/ <?= PresentacionDatosSire::formatearMonto($tipo['base']) ?></td><td>S/ <?= PresentacionDatosSire::formatearMonto($tipo['igv']) ?></td><td>S/ <?= PresentacionDatosSire::formatearMonto($tipo['total']) ?></td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody></table></div>
          </section>
        </div>
        <div data-tab-panel="rce-detalle" <?= $abrirDetalle ? '' : 'hidden' ?>>
          <section class="panel module-panel">
            <div class="reference-table-title" style="padding:0 18px"><div>
              <h2>Detalle de comprobantes RCE</h2>
              <p><?= $esGlobal ? 'Una página completa por empresa. Siguiente muestra la próxima empresa, sin dividir sus comprobantes.' : 'Comprobantes importados del archivo SIRE del período.' ?></p>
              <?php if ($esGlobal && $clientePagina !== null): ?>
                <p><strong>Empresa <?= $pagina ?> de <?= $totalPaginas ?>: <?= $h($clientePagina['razon_social']) ?></strong> · RUC <?= $h($clientePagina['ruc']) ?></p>
              <?php endif; ?>
              <?php if ($esGlobal && $tipoFiltrado !== ''): ?><a class="reference-btn" href="<?= $h($urlDetalle(1)) ?>">Quitar filtro: <?= $h($tipoFiltrado) ?></a><?php endif; ?>
              <button type="button" class="reference-btn sire-clear-filter" hidden>Ver todos</button>
            </div><div class="reference-table-actions"><span class="reference-badge"><?= $esGlobal ? count($detallesVisibles) : $totalDetallesMostrados ?> comprobantes<?= $esGlobal ? ' de esta empresa' : '' ?></span>
              <a class="reference-btn" data-rce-excel="detalle" href="<?= $h($excelBase . '&modo=detalle') ?>">⇩ Excel detalle</a>
              <?php if (!$esAnual && !$esGlobal): ?><a class="reference-btn" data-rce-excel="original" href="<?= $h($excelBase . '&modo=original') ?>">Excel original</a><?php endif; ?>
            </div></div>
            <?php require __DIR__ . '/descarga_periodo_sire.php'; ?>
            <form id="rceFiltrosDetalle" class="rce-filtros-detalle">
              <div class="rce-filtros-titulo"><strong>Filtros de comprobantes</strong><small>Busca dentro de los registros cargados de esta página.</small></div>
              <label>RUC / Proveedor<input type="search" name="proveedor" placeholder="RUC o razón social"></label>
              <label>Tipo documento<select name="tipo"><option value="">Todos</option><?php foreach($tipos as $tipo): ?><option value="<?= $h($tipo['tipo']) ?>"><?= $h($tipo['tipo']) ?></option><?php endforeach; ?></select></label>
              <label>Serie<input name="serie" placeholder="Ej. F001"></label>
              <label>Número<input name="numero" placeholder="Número de comprobante"></label>
              <label>Fecha desde<input type="date" name="desde"></label>
              <label>Fecha hasta<input type="date" name="hasta"></label>
              <label>Importe mínimo (S/)<input type="number" min="0" step="0.01" name="minimo"></label>
              <label>Importe máximo (S/)<input type="number" min="0" step="0.01" name="maximo"></label>
              <div class="rce-filtros-botones"><button class="reference-btn primary" type="submit">Buscar</button><button class="reference-btn" type="reset">Limpiar</button></div>
              <p id="rceFiltrosEstado" role="status" aria-live="polite"></p>
            </form>
            <div class="rce-seleccion-controles" id="rceSeleccionControles" data-csrf="<?= $h(asesconCsrf('csrf_rce_lote')) ?>">
              <label class="rce-seleccion-todos"><input type="checkbox" id="rceMarcarTodos"> Seleccionar visibles (máximo 100)</label>
              <label class="rce-seleccion-cantidad">Elegir cantidad
                <select id="rceSeleccionCantidad" aria-label="Elegir cuántos comprobantes seleccionar">
                  <option value="">Elegir</option>
                  <option value="10">10</option>
                  <option value="20">20</option>
                  <option value="50">50</option>
                  <option value="100">100</option>
                  <option value="todos">Todos los visibles (máximo 100)</option>
                </select>
              </label>
              <span id="rceCantidadSeleccionados" aria-live="polite">0 comprobantes seleccionados</span>
              <button type="button" class="reference-btn" id="rceLimpiarSeleccion" disabled>Deseleccionar todos</button>
              <div class="rce-seleccion-acciones">
                <button type="button" class="reference-btn" id="rceLotePdf" disabled>⇩ PDF seleccionados (ZIP)</button>
                <button type="button" class="reference-btn" id="rceLoteExcel" disabled>⇩ Excel seleccionados</button>
                <button type="button" class="reference-btn" id="rceLoteIndividual" disabled>⇩ PDF individuales</button>
                <div class="rce-enviar-grupo" id="rceEnviarGrupo">
                  <button type="button" class="reference-btn primary" id="rceLoteEnviar" aria-expanded="false" aria-controls="rceEnviarOpciones" disabled>Enviar ▾</button>
                  <div class="rce-enviar-opciones" id="rceEnviarOpciones" hidden>
                    <?php if ($puedeEnviarCorreo): ?><button type="button" data-rce-enviar-correo>Enviar por correo</button><?php endif; ?>
                    <button type="button" data-rce-enviar-whatsapp>Compartir por WhatsApp</button>
                  </div>
                </div>
              </div>
              <p class="reference-subtle rce-seleccion-ayuda">Selecciona hasta 100 comprobantes visibles para descargar un ZIP, un Excel o PDFs individuales. Las descargas individuales pueden requerir permiso del navegador.</p>
              <p id="rceSeleccionEstado" role="status" aria-live="polite"></p>
            </div>
            <div class="table-container"><table><thead><tr>
              <th class="sire-columna-seleccion">Selección</th>
              <?php if ($esGlobal): ?><th>Empresa ASESCON</th><?php endif; ?>
              <th>Fecha de emisión</th><th>Tipo</th><th>Serie</th><th>Número</th><th>RUC proveedor</th><th>Proveedor</th><th>Base imponible</th><th>IGV</th><th>Total</th><th>Estado</th><th>Acciones</th>
            </tr></thead><tbody>
              <?php if ($detallesVisibles === []): ?><tr><td colspan="<?= $esGlobal ? 13 : 12 ?>"><div class="reference-empty">No hay comprobantes visibles para este filtro.</div></td></tr>
              <?php else: foreach ($detallesVisibles as $detalle): ?>
                <tr class="sire-detail-row" data-sire-tipo="<?= $h($detalle['tipo_comprobante'] ?? '—') ?>" data-rce-proveedor="<?= $h(($detalle['ruc_proveedor'] ?? '') . ' ' . ($detalle['razon_social_proveedor'] ?? '')) ?>" data-rce-serie="<?= $h($detalle['serie'] ?? '') ?>" data-rce-numero="<?= $h($detalle['numero'] ?? '') ?>" data-rce-fecha="<?= $h($detalle['fecha_emision'] ?? '') ?>" data-rce-total="<?= (float)($detalle['importe_total'] ?? 0) ?>">
                  <td class="sire-columna-seleccion"><input type="checkbox" class="sire-seleccion-fila rce-seleccionar-fila" data-cliente="<?= $esGlobal ? (int)($detalle['id_cliente'] ?? 0) : $idCliente ?>" data-detalle="<?= (int)($detalle['id_detalle'] ?? 0) ?>" aria-label="Seleccionar comprobante <?= $h(($detalle['serie'] ?? '') . '-' . ($detalle['numero'] ?? '')) ?>"></td>
                  <?php if ($esGlobal): ?><td><strong><?= $h($detalle['empresa_asescon'] ?? '—') ?></strong><br><small><?= $h($detalle['ruc_empresa'] ?? '') ?></small></td><?php endif; ?>
                  <td><?= !empty($detalle['fecha_emision']) ? $h(date('d/m/Y', strtotime((string)$detalle['fecha_emision']))) : '—' ?></td>
                  <td><?= $h($detalle['tipo_comprobante'] ?? '—') ?></td><td><?= $h($detalle['serie'] ?? '—') ?></td><td><?= $h($detalle['numero'] ?? '—') ?></td>
                  <td><?= $h($detalle['ruc_proveedor'] ?? '—') ?></td><td><strong><?= $h($detalle['razon_social_proveedor'] ?? '—') ?></strong></td>
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
                  $rucEmisorFila = $detalle['ruc_proveedor'] ?? '';
                  $numeroFila = trim(($detalle['serie'] ?? '') . '-' . ($detalle['numero'] ?? ''), '-');
                  ?>
                  <?php require __DIR__ . '/botones_comprobante_sire.php'; ?>
                </tr>
              <?php endforeach; endif; ?>
            </tbody></table></div>
            <?php if ($esGlobal && $clientePagina !== null): ?>
              <nav class="rce-paginacion" aria-label="Páginas por empresa">
                <?php if ($pagina > 1): ?><a class="reference-btn" href="<?= $h($urlDetalle($pagina - 1, $tipoFiltrado)) ?>">Anterior empresa</a><?php endif; ?>
                <span>Empresa <?= $pagina ?> de <?= $totalPaginas ?> · <?= $h($clientePagina['razon_social']) ?></span>
                <?php if ($pagina < $totalPaginas): ?><a class="reference-btn" href="<?= $h($urlDetalle($pagina + 1, $tipoFiltrado)) ?>">Siguiente empresa</a><?php endif; ?>
              </nav>
            <?php endif; ?>
          </section>
        </div>
      </div>
      <?php require __DIR__ . '/dialogos_comprobante_sire.php'; ?>
      <?php if ($puedeEnviarCorreo): ?>
      <dialog id="rceCorreoLote" class="rce-dialogo" aria-labelledby="rceCorreoLoteTitulo">
        <form id="rceCorreoLoteFormulario" autocomplete="off">
          <h2 id="rceCorreoLoteTitulo">Enviar comprobantes RCE seleccionados</h2>
          <p data-rce-cantidad></p>
          <p>Se adjuntará un archivo ZIP con los PDF informativos, sin cambiar el diseño de los documentos.</p>
          <label for="rceCorreoLoteDestino">Correo del destinatario</label>
          <input id="rceCorreoLoteDestino" type="email" name="destinatario" required maxlength="254" autocomplete="email">
          <p data-rce-estado role="status" aria-live="polite"></p>
          <div class="rce-dialogo-acciones">
            <button type="button" class="reference-btn" data-rce-cerrar>Cancelar</button>
            <button type="submit" class="reference-btn primary">Enviar por correo</button>
          </div>
        </form>
      </dialog>
      <?php endif; ?>
      <dialog id="rceWhatsAppLote" class="rce-dialogo" aria-labelledby="rceWhatsAppLoteTitulo">
        <h2 id="rceWhatsAppLoteTitulo">Compartir comprobantes seleccionados</h2>
        <p data-rce-cantidad></p>
        <p>WhatsApp Web no adjunta archivos automáticamente. Descarga el ZIP y adjúntalo al chat; en dispositivos compatibles también puedes usar Compartir.</p>
        <p data-rce-estado role="status" aria-live="polite"></p>
        <div class="rce-dialogo-acciones">
          <button type="button" class="reference-btn" data-rce-cerrar>Cerrar</button>
          <button type="button" class="reference-btn" id="rceCompartirLote" hidden>Compartir archivo</button>
          <button type="button" class="reference-btn" id="rceDescargarLoteWhatsapp" disabled>Descargar ZIP</button>
          <a class="reference-btn primary" href="https://web.whatsapp.com/" target="_blank" rel="noopener noreferrer">Abrir WhatsApp</a>
        </div>
      </dialog>
      <?php $moduloArchivos = 'RCE'; require __DIR__ . '/archivo_comprobante.php'; ?>
</div>
