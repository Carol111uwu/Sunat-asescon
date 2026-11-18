<?php
declare(strict_types=1);
require_once __DIR__ . '/../../configuracion/inicializacion.php';
asesconExigirLogin();
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
if (($_GET['fragmento'] ?? '') === 'resultados') {
    header('Cache-Control: no-store');
    require __DIR__ . '/componentes/resultados_rvie.php';
    return;
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="asescon-csrf-sire-masivo" content="<?= $h(asesconCsrf('csrf_sire_masivo')) ?>">
  <meta name="asescon-csrf-sire" content="<?= $h($csrfSire) ?>">
  <title>RVIE SUNAT — Ventas — ASESCON</title>
  <link rel="stylesheet" href="<?= htmlspecialchars(asesconRecurso('publico/css/base/estructura_panel.css'), ENT_QUOTES, 'UTF-8') ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(asesconRecurso('publico/css/modulos/sunat.css'), ENT_QUOTES, 'UTF-8') ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(asesconRecurso('publico/css/modulos/descarga_masiva_sire.css'), ENT_QUOTES, 'UTF-8') ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(asesconRecurso('publico/css/modulos/rvie_centro.css'), ENT_QUOTES, 'UTF-8') ?>">
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
          'descripcion' => 'Ventas obtenidas mediante consulta a SUNAT SIRE. Se muestran los datos guardados y se consultan los períodos pendientes; la aceptación y generación del registro no se realizan aquí.',
      ];
      require __DIR__ . '/../componentes/estado_integracion.php';
      ?>
      <div class="page-header"><div>
        <h1>RVIE SUNAT — Ventas</h1>
        <p>Consulta las ventas de SUNAT SIRE por empresa y período.</p>
      </div></div>

      <section class="reference-panel rvie-query" aria-label="Consulta de ventas">
        <div class="rvie-query-header">
          <div><h2 class="reference-section-title">Ventas SUNAT</h2><p class="reference-subtle">Consulta períodos anteriores guardados y completa los datos que falten en SUNAT.</p></div>
          <div class="rvie-top-actions">
            <button class="reference-btn" type="button" id="rvieVerGuardados" aria-controls="rvieInventario" aria-expanded="false" hidden>Ver datos guardados</button>
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
              <button type="submit" class="reference-btn primary" id="rvieVerConsultar" <?= $puedeEjecutarSire ? '' : 'data-solo-lectura="1"' ?>>Ver/consultar</button>
            </div>
            <small class="reference-subtle">Se muestran primero los resultados guardados. SUNAT recibe solo consultas de períodos faltantes.</small>
            <small id="rvieAvisoSeleccion" class="reference-subtle" role="status" hidden></small>
            <noscript><p class="reference-subtle">Puedes ver los resultados guardados con los filtros. Para consultar SUNAT SIRE, activa JavaScript.</p></noscript>
          </div>
          <div id="rviePendientesAnuales" class="sire-pendientes-anuales" hidden role="status">
            <span id="rviePendientesTexto"></span>
            <button type="button" class="reference-btn sire-boton-recuperar" id="rvieRecuperarPendientes">Recuperar meses pendientes</button>
          </div>
        </form>
      </section>

      <section id="rvieInventario" class="reference-panel" aria-labelledby="rvieInventarioTitulo" hidden>
        <h2 class="reference-section-title" id="rvieInventarioTitulo">Datos guardados por empresa y período</h2>
        <p class="reference-subtle" role="status"></p>
        <div class="table-container" hidden><table>
          <thead><tr><th>RUC</th><th>Empresa</th><th>Año</th><th>Meses disponibles</th></tr></thead><tbody></tbody>
        </table></div>
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

      <?php require __DIR__ . '/componentes/resultados_rvie.php'; ?>
      <footer>© <?= (int)(new DateTimeImmutable("now", new DateTimeZone("America/Lima")))->format("Y") ?> ASESCON - Todos los derechos reservados.</footer>
    </section>
  </main>
</div>
<script src="<?= htmlspecialchars(asesconRecurso('publico/js/navegacion/menu_lateral.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(asesconRecurso('publico/js/navegacion/busqueda_clientes.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(asesconRecurso('publico/js/navegacion/menu_movil.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(asesconRecurso('publico/js/interfaz/componentes_interfaz.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(asesconRecurso('publico/js/sunat/sincronizacion_sire.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(asesconRecurso('publico/js/sunat/pendientes_anuales_sire.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(asesconRecurso('publico/js/sunat/datos_guardados_sire.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(asesconRecurso('publico/js/sunat/rvie_centro.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(asesconRecurso('publico/js/sunat/rce_comprobante.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(asesconRecurso('publico/js/sunat/descarga_masiva_sire.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(asesconRecurso('publico/js/interfaz/tablas_adaptables.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body></html>
