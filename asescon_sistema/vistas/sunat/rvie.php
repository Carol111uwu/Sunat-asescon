<?php
declare(strict_types=1);
require_once __DIR__ . '/../../configuracion/inicializacion.php';
require_once __DIR__ . '/../../controladores/sunat/ControladorVistaLibroSire.php';

$datosVista = (new ControladorVistaLibroSire($conexion))->preparar('RVIE', $_GET);
extract($datosVista, EXTR_SKIP);
$h = static fn(mixed $v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$parametros = [
    'cliente' => $idCliente,
    'mes' => $mes,
    'anio' => $anio,
    'excluir_anteriores' => $excluirAnteriores ? '1' : '0',
];
$urlDetalle = static function (int $paginaDestino, string $tipo = '') use ($parametros): string {
    return '?' . http_build_query(array_merge($parametros, [
        'pagina' => $paginaDestino,
        'tipo' => $tipo,
        'vista' => 'detalle',
    ]), '', '&', PHP_QUERY_RFC3986);
};
$totalDetallesMostrados = $esGlobal ? $totalDetallesGlobales : count($detallesVisibles);
$totalPaginas = $esGlobal ? max(1, count($clientesPaginados)) : 1;
$abrirDetalle = (string)($_GET['vista'] ?? '') === 'detalle';
$puedeEnviarCorreo = asesconTienePermiso($conexion, 'RVIE', 'editar');
$apiComprobante = '../../api/sunat/comprobante_rvie.php';
$csrfCorreo = 'csrf_rvie_correo';
$rucEmpresaPorCliente = array_column($clientes, 'ruc', 'id_cliente');
require __DIR__ . '/componentes/acciones_comprobante_rce.php';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="asescon-csrf-sire" content="<?= $h($csrfSire) ?>">
  <title>RVIE SUNAT — Ventas — ASESCON</title>
  <link rel="stylesheet" href="../../publico/css/base/estructura_panel.css?v=20261028_ui3">
  <link rel="stylesheet" href="../../publico/css/modulos/sunat.css">
  <link rel="stylesheet" href="../../publico/css/modulos/descarga_masiva_sire.css?v=<?= (int)@filemtime(__DIR__ . '/../../publico/css/modulos/descarga_masiva_sire.css') ?>">
  <link rel="stylesheet" href="../../publico/css/modulos/rvie_centro.css?v=<?= (int)@filemtime(__DIR__ . '/../../publico/css/modulos/rvie_centro.css') ?>">
</head>
<body>
<div class="app">
  <?php require __DIR__ . '/../componentes/menu_lateral.php'; ?>
  <main class="main-content">
    <?php require __DIR__ . '/../componentes/barra_superior.php'; ?>
    <section class="content">
      <?php
      $avisoModulo = [
          'etiqueta' => 'API SIRE: propuesta RVIE',
          'descripcion' => 'Ventas obtenidas mediante consulta a SUNAT SIRE. Cada solicitud de este módulo inicia una consulta oficial; la aceptación y generación del registro no se realizan aquí.',
      ];
      require __DIR__ . '/../componentes/estado_integracion.php';
      ?>
      <div class="page-header"><div>
        <h1>RVIE SUNAT — Ventas</h1>
        <p>Consulta las ventas directamente a SUNAT SIRE por empresa y período.</p>
      </div></div>

      <section class="reference-panel rvie-query" aria-label="Consulta de ventas">
        <div class="rvie-query-header">
          <div><h2 class="reference-section-title">Ventas SUNAT</h2><p class="reference-subtle">Cada vez que pulses Consultar, solicitaremos el período a SUNAT SIRE.</p></div>
          <div class="rvie-top-actions">
            <button class="reference-btn" type="button" id="rvieVerEstado" aria-controls="rvieProceso" aria-expanded="false">Ver estado de consultas</button>
          </div>
        </div>

        <form method="get" id="rvieConsultaFormulario" data-sire-usuario="<?= (int)($_SESSION['id_usuario'] ?? 0) ?>">
          <div class="reference-filter-grid three rvie-filtros">
            <label class="reference-field"><span>Cliente</span>
              <select name="cliente" id="rvieCliente">
                <option value="0">Todos los clientes</option>
                <?php foreach ($clientes as $c): ?>
                  <option value="<?= (int)$c['id_cliente'] ?>" data-ruc="<?= $h($c['ruc']) ?>" data-nombre="<?= $h($c['razon_social']) ?>" <?= $idCliente === (int)$c['id_cliente'] ? 'selected' : '' ?>><?= $h($c['ruc'] . ' — ' . $c['razon_social']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="reference-field"><span>Mes</span>
              <select name="mes" id="rvieMes">
                <?php foreach ($mesesUi as $codigo => $nombre): $codigo = (string)$codigo; ?>
                  <option value="<?= $h($codigo) ?>" <?= $codigo === $mes ? 'selected' : '' ?>><?= $h($nombre) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="reference-field"><span>Año</span>
              <select name="anio" id="rvieAnio">
                <?php foreach ($aniosUi as $a): $a = (string)$a; ?>
                  <option value="<?= $h($a) ?>" <?= $a === $anio ? 'selected' : '' ?>><?= $h($a) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
          <input type="hidden" name="excluir_anteriores" value="<?= $excluirAnteriores ? '1' : '0' ?>">
          <div class="rvie-consulta-actions">
            <div class="rvie-dos-botones">
              <button type="submit" class="reference-btn primary" id="rvieVerConsultar" disabled <?= $puedeEjecutarSire ? '' : 'data-solo-lectura="1"' ?>>Consultar</button>
            </div>
            <small class="reference-subtle">La consulta anual solicita cada mes a SUNAT en orden. Si alguno queda pendiente, puedes reintentar solo ese mes sin detener el resto.</small>
            <small id="rvieAvisoSeleccion" class="reference-subtle" role="status" hidden></small>
            <noscript><p class="reference-subtle">Para consultar SUNAT SIRE debes activar JavaScript.</p></noscript>
          </div>
          <div id="rviePendientesAnuales" class="sire-pendientes-anuales" hidden role="status">
            <span id="rviePendientesTexto"></span>
            <button type="button" class="reference-btn sire-boton-recuperar" id="rvieRecuperarPendientes">Recuperar meses pendientes</button>
          </div>
        </form>
      </section>

      <section id="rvieProceso" class="reference-panel rvie-proceso" hidden aria-live="polite">
        <div class="rvie-proceso-encabezado"><div>
          <h2 class="reference-section-title">Estado de consulta RVIE</h2>
          <p id="syncStatusRVIE" role="status">Esperando consulta.</p>
        </div><button type="button" class="reference-btn sire-boton-pausa" id="rviePausar" hidden>Pausar después del mes actual</button></div>
        <progress id="rvieBarraProgreso" value="0" max="100" aria-label="Períodos revisados, incluidos los que quedaron pendientes o con errores"></progress>
        <div class="rvie-proceso-contadores" id="rvieContadores"></div>
        <ol id="rvieListaProcesos" class="rvie-proceso-lista"></ol>
      </section>

      <div class="reference-status-strip" id="rvieEstadoGuardado" tabindex="-1">
        <div class="status-main">
          <?php $hayImportacion = in_array((string)($actual['estado'] ?? ''), ['PROCESADO','GUARDADO','ANUAL'], true); ?>
          <span class="reference-dot <?= $hayImportacion ? '' : 'warning' ?>"></span>
          <span><strong><?= $hayImportacion ? ((int)$actual['cantidad_registros'] === 0 ? 'Propuesta SUNAT: 0 comprobantes' : 'Resultados de la consulta') : 'Sin resultados para mostrar' ?> — <?= $h($periodoContexto) ?></strong>
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
                  <?php require __DIR__ . '/componentes/botones_comprobante_sire.php'; ?>
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
      <?php require __DIR__ . '/componentes/dialogos_comprobante_sire.php'; ?>
      <?php $moduloArchivos = 'RVIE'; require __DIR__ . '/componentes/archivo_comprobante.php'; ?>
      <footer>© <?= (int)(new DateTimeImmutable("now", new DateTimeZone("America/Lima")))->format("Y") ?> ASESCON - Todos los derechos reservados.</footer>
    </section>
  </main>
</div>
<script src="../../publico/js/navegacion/menu_lateral.js"></script>
<script src="../../publico/js/navegacion/busqueda_clientes.js?v=20261014_1"></script>
<script src="../../publico/js/navegacion/menu_movil.js"></script>
<script src="../../publico/js/interfaz/componentes_interfaz.js?v=20260915_3"></script>
<script src="../../publico/js/sunat/sincronizacion_sire.js?v=<?= (int)@filemtime(__DIR__ . '/../../publico/js/sunat/sincronizacion_sire.js') ?>"></script>
<script src="../../publico/js/sunat/pendientes_anuales_sire.js?v=<?= (int)@filemtime(__DIR__ . '/../../publico/js/sunat/pendientes_anuales_sire.js') ?>"></script>
<script src="../../publico/js/sunat/rvie_centro.js?v=<?= (int)@filemtime(__DIR__ . '/../../publico/js/sunat/rvie_centro.js') ?>"></script>
<script src="../../publico/js/sunat/rce_comprobante.js?v=<?= (int)filemtime(__DIR__ . '/../../publico/js/sunat/rce_comprobante.js') ?>"></script>
<script src="../../publico/js/sunat/descarga_masiva_sire.js?v=<?= (int)@filemtime(__DIR__ . '/../../publico/js/sunat/descarga_masiva_sire.js') ?>"></script>
<script src="../../publico/js/interfaz/tablas_adaptables.js?v=<?= (int)@filemtime(__DIR__ . '/../../publico/js/interfaz/tablas_adaptables.js') ?>"></script>
</body></html>
