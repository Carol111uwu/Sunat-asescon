<?php
declare(strict_types=1);
require_once __DIR__ . '/../../configuracion/inicializacion.php';
asesconExigirLogin();
require_once __DIR__ . '/../../controladores/sunat/ControladorVistaLibroSire.php';

$datosVista = (new ControladorVistaLibroSire($conexion))->preparar('RCE', $_GET);
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
$puedeEnviarCorreo = asesconTienePermiso($conexion, 'RCE', 'editar');
$apiComprobante = '../../api/sunat/comprobante_rce.php';
$csrfCorreo = 'csrf_rce_correo';
require __DIR__ . '/componentes/acciones_comprobante_rce.php';
if (($_GET['fragmento'] ?? '') === 'resultados') {
    header('Cache-Control: no-store');
    require __DIR__ . '/componentes/resultados_rce.php';
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
  <title>RCE SUNAT — Compras — ASESCON</title>
  <link rel="stylesheet" href="<?= htmlspecialchars(asesconRecurso('publico/css/base/estructura_panel.css'), ENT_QUOTES, 'UTF-8') ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(asesconRecurso('publico/css/modulos/sunat.css'), ENT_QUOTES, 'UTF-8') ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(asesconRecurso('publico/css/modulos/descarga_masiva_sire.css'), ENT_QUOTES, 'UTF-8') ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(asesconRecurso('publico/css/modulos/rce_centro.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<div class="app">
  <?php require __DIR__ . '/../componentes/menu_lateral.php'; ?>
  <main class="main-content">
    <?php require __DIR__ . '/../componentes/barra_superior.php'; ?>
    <section class="content">
      <?php
      $avisoModulo = [
          'etiqueta' => 'API SIRE: propuesta RCE',
          'descripcion' => 'Compras obtenidas mediante consulta a SUNAT SIRE. Se muestran los datos guardados y se consultan los períodos pendientes; la aceptación y generación del registro no se realizan aquí.',
      ];
      require __DIR__ . '/../componentes/estado_integracion.php';
      ?>
      <div class="page-header"><div>
        <h1>RCE SUNAT — Compras</h1>
        <p>Consulta las compras de SUNAT SIRE por empresa y período.</p>
      </div></div>

      <section class="reference-panel rce-query" aria-label="Consulta de compras">
        <div class="rce-query-header">
          <div><h2 class="reference-section-title">Compras SUNAT</h2><p class="reference-subtle">Consulta los datos guardados hoy y completa los períodos que falten.</p></div>
          <div class="rce-top-actions">
            <button class="reference-btn" type="button" id="rceVerGuardados" aria-controls="rceInventario" aria-expanded="false" hidden>Ver datos guardados</button>
            <button class="reference-btn" type="button" id="rceVerEstado" aria-controls="rceProceso" aria-expanded="false">Ver estado de consultas</button>
          </div>
        </div>

        <form method="get" id="rceConsultaFormulario" data-sire-usuario="<?= (int)($_SESSION['id_usuario'] ?? 0) ?>">
          <div class="reference-filter-grid three rce-filtros">
            <label class="reference-field"><span>Cliente</span>
              <select name="cliente" id="rceCliente">
                <option value="0">Todos los clientes</option>
                <?php foreach ($clientes as $c): ?>
                  <option value="<?= (int)$c['id_cliente'] ?>" data-ruc="<?= $h($c['ruc']) ?>" data-nombre="<?= $h($c['razon_social']) ?>" <?= $idCliente === (int)$c['id_cliente'] ? 'selected' : '' ?>><?= $h($c['ruc'] . ' — ' . $c['razon_social']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="reference-field"><span>Mes</span>
              <select name="mes" id="rceMes">
                <?php foreach ($mesesUi as $codigo => $nombre): $codigo = (string)$codigo; ?>
                  <option value="<?= $h($codigo) ?>" <?= $codigo === $mes ? 'selected' : '' ?>><?= $h($nombre) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="reference-field"><span>Año</span>
              <select name="anio" id="rceAnio">
                <?php foreach ($aniosUi as $a): $a = (string)$a; ?>
                  <option value="<?= $h($a) ?>" <?= $a === $anio ? 'selected' : '' ?>><?= $h($a) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
          <input type="hidden" name="excluir_anteriores" value="<?= $excluirAnteriores ? '1' : '0' ?>">
          <div class="rce-consulta-actions">
            <div class="rce-dos-botones">
              <button type="submit" class="reference-btn primary" id="rceVerConsultar" <?= $puedeEjecutarSire ? '' : 'data-solo-lectura="1"' ?>>Ver/consultar</button>
            </div>
            <small class="reference-subtle">Se muestran primero los resultados guardados. SUNAT recibe solo consultas de períodos faltantes.</small>
            <small id="rceAvisoSeleccion" class="reference-subtle" role="status" hidden></small>
            <noscript><p class="reference-subtle">Puedes ver los resultados guardados con los filtros. Para consultar SUNAT SIRE, activa JavaScript.</p></noscript>
          </div>
          <div id="rcePendientesAnuales" class="sire-pendientes-anuales" hidden role="status">
            <span id="rcePendientesTexto"></span>
            <button type="button" class="reference-btn sire-boton-recuperar" id="rceRecuperarPendientes">Recuperar meses pendientes</button>
          </div>
        </form>
      </section>

      <section id="rceInventario" class="reference-panel" aria-labelledby="rceInventarioTitulo" hidden>
        <h2 class="reference-section-title" id="rceInventarioTitulo">Datos guardados de hoy</h2>
        <p class="reference-subtle" role="status"></p>
        <div class="table-container" hidden><table>
          <thead><tr><th>RUC</th><th>Empresa</th><th>Año</th><th>Meses disponibles</th></tr></thead><tbody></tbody>
        </table></div>
      </section>

      <section id="rceProceso" class="reference-panel rce-proceso" hidden aria-live="polite">
        <div class="rce-proceso-encabezado"><div>
          <h2 class="reference-section-title">Estado de consulta RCE</h2>
          <p id="syncStatusRCE" role="status">Esperando consulta.</p>
        </div><button type="button" class="reference-btn sire-boton-pausa" id="rcePausar" hidden>Pausar después del mes actual</button></div>
        <progress id="rceBarraProgreso" value="0" max="100" aria-label="Períodos revisados, incluidos los que quedaron pendientes o con errores"></progress>
        <div class="rce-proceso-contadores" id="rceContadores"></div>
        <ol id="rceListaProcesos" class="rce-proceso-lista"></ol>
      </section>

      <?php require __DIR__ . '/componentes/resultados_rce.php'; ?>
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
<script src="<?= htmlspecialchars(asesconRecurso('publico/js/sunat/rce_centro.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(asesconRecurso('publico/js/sunat/rce_comprobante.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(asesconRecurso('publico/js/sunat/descarga_masiva_sire.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(asesconRecurso('publico/js/sunat/rce_seleccion.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(asesconRecurso('publico/js/sunat/rce_descarga_individual.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(asesconRecurso('publico/js/interfaz/tablas_adaptables.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body></html>
