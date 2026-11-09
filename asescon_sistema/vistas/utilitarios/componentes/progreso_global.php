<?php
$syncEsc=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$syncEnlaces=[
 'RCE'=>asesconUrl('vistas/sunat/rce.php'),'RVIE'=>asesconUrl('vistas/sunat/rvie.php'),
 'SOL'=>asesconUrl('vistas/buzones/buzon_sol_sunat.php'),'SUNAFIL'=>asesconUrl('vistas/buzones/buzon_sunafil.php'),
 'DECLARACIONES'=>asesconUrl('vistas/sunat/control_declaraciones.php'),
];
?>
<link rel="stylesheet" href="<?=$syncEsc(asesconUrl('publico/css/modulos/sincronizacion.css'))?>?v=<?=(int)filemtime(__DIR__.'/../../../publico/css/modulos/sincronizacion.css')?>">
<section class="reference-panel compact sync-panel" id="syncPanel"
 data-endpoint="<?=$syncEsc(asesconUrl('api/sincronizacion/estado_global.php'))?>"
 data-enlaces="<?=$syncEsc(json_encode($syncEnlaces,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR))?>" aria-label="Progreso de sincronización por empresa">
 <div class="sync-heading"><div><h2>Progreso de sincronización</h2><p class="reference-subtle">Filtra empresas, períodos y módulos para comprobar resultados y consultas pendientes.</p></div><p id="syncCronEstado" class="sync-cron" role="status">Consultando Cron…</p></div>
 <div class="sync-filtros">
  <div class="reference-field sync-empresa-selector"><span>Empresa</span>
   <button type="button" class="sync-selector-boton" id="syncEmpresaBoton" aria-expanded="false" aria-controls="syncEmpresaPanel">Empresas · Todas ▾</button>
   <div id="syncEmpresaPanel" class="sync-selector-panel" hidden>
    <input id="syncBuscar" type="search" placeholder="Buscar por RUC, nombre, razón social o celular" aria-label="Buscar empresa" autocomplete="off">
    <button type="button" class="sync-opcion-todas" id="syncTodas">Todas las empresas</button>
    <div id="syncOpcionesEmpresa" class="sync-opciones-empresa" role="listbox" aria-label="Empresas disponibles"></div>
   </div>
  </div>
  <label class="reference-field"><span>Mes / Año</span><select id="syncPeriodo"><option value="todos">Todos los meses del año actual</option></select></label>
  <label class="reference-field"><span>Módulos</span><select id="syncModulo">
   <option value="TODOS">Todos</option><option value="RVIE">Ventas RVIE</option><option value="RCE">Compras RCE</option>
   <option value="SOL">Buzón SOL</option><option value="SUNAFIL">Buzón SUNAFIL</option>
   <option value="BUZONES">Buzones (SOL y SUNAFIL)</option><option value="DECLARACIONES">Declaraciones</option>
   <option value="PADRONES">Padrones generales (RUC y SSCO)</option><option value="PADRON_RUC">Padrón RUC</option><option value="SSCO">SSCO</option>
  </select></label>
 </div>
 <p class="reference-subtle">Selecciona un período desde 2021 para comprobar su estado. La barra de progreso muestra el lote cron actual.</p>
 <p id="syncGlobalEstado" role="status" aria-live="polite">Consultando el estado de sincronización…</p>
 <progress id="syncGlobalBarra" value="0" max="100" aria-label="Avance de las tareas programadas"></progress>
 <div id="syncGlobalCifras" class="sync-metricas"></div>
 <p id="syncGlobalAviso" class="sync-aviso" role="status" hidden></p>
 <div id="syncEmpresas" class="sync-empresas"></div>
 <p id="syncSinResultados" class="reference-subtle" hidden>No hay empresas que coincidan con estos filtros.</p>
 <small class="sync-nota">Fases en orden: ventas → compras → declaraciones → padrones → buzones. El cron planifica el año vigente; el filtro puede consultar el historial desde 2021 sin crear trabajos antiguos. Actualización cada 15 segundos. Puedes salir de esta página: los trabajos continúan cuando el cron está activo.</small>
 <noscript>Activa JavaScript para consultar el progreso. Las tareas programadas continúan en el servidor.</noscript>
</section>
<script src="<?=$syncEsc(asesconUrl('publico/js/sincronizacion/progreso_total.js'))?>?v=<?=(int)filemtime(__DIR__.'/../../../publico/js/sincronizacion/progreso_total.js')?>" defer></script>
