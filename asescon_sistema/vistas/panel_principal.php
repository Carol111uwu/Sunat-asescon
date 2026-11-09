<?php

declare(strict_types=1);
require_once __DIR__ . '/../configuracion/inicializacion.php';
require_once __DIR__ . '/../controladores/panel/ControladorPanelPrincipal.php';

$datosDashboard=(new ControladorPanelPrincipal($conexion))->preparar();
extract($datosDashboard, EXTR_SKIP);
require_once __DIR__.'/../servicios/textos/ArchivoTextos.php';
$ocultasDashboard=(new ArchivoTextos((int)asesconUsuarioActual($conexion)['id_usuario']))->listar();
$prioridadesHoy=array_values(array_filter($prioridadesHoy,static fn($t)=>!isset($ocultasDashboard['tarea:'.($t['id_tarea']??0)])));
$puedeEditarTareasDashboard=asesconTienePermiso($conexion,'TAREAS','editar');
$puedeEliminarTareasDashboard=asesconTienePermiso($conexion,'TAREAS','eliminar');
$csrfTareasDashboard=$puedeEliminarTareasDashboard?asesconCsrf('csrf_tareas'):'';
$puedeControlarSyncDashboard=asesconEsPrincipal($conexion);
$mensajeSyncDashboard=$_SESSION['mensaje_sync_general']??null;
unset($_SESSION['mensaje_sync_general']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ASESCON</title>
    <link rel="stylesheet" href="../publico/css/base/estructura_panel.css?v=<?php echo (int)@filemtime(__DIR__.'/../publico/css/base/estructura_panel.css'); ?>">
    <link rel="stylesheet" href="../publico/css/seguridad/recordatorio_contrasena.css?v=<?php echo (int)@filemtime(__DIR__.'/../publico/css/seguridad/recordatorio_contrasena.css'); ?>">
    <link rel="stylesheet" href="../publico/css/modulos/sincronizacion_dashboard.css?v=<?php echo (int)@filemtime(__DIR__.'/../publico/css/modulos/sincronizacion_dashboard.css'); ?>">
</head>
<body>
<div class="app">
    <?php require __DIR__ . '/componentes/menu_lateral.php'; ?>
    <main class="main-content">
        <?php require __DIR__ . '/componentes/barra_superior.php'; ?>
        <section class="content">
            <?php require __DIR__ . '/componentes/alerta_cambio_contrasena.php'; ?>
            <div class="page-header">
                <div><h1>NotiControl Contable</h1><p>Tu día contable, organizado y bajo control</p></div>
                <?php if($puedeControlarSyncDashboard):?>
                <section class="sync-dashboard-mini" id="syncDashboardMini" data-endpoint="<?php echo htmlspecialchars(asesconUrl('api/sincronizacion/estado_global.php').'?compacto=1',ENT_QUOTES,'UTF-8'); ?>" aria-label="Sincronización total en segundo plano">
                    <div class="sync-dashboard-mini-top"><strong>Sincronización total</strong><small id="syncMiniPorcentaje">Esperando estado…</small></div>
                    <progress id="syncMiniBarra" value="0" max="100" aria-label="Porcentaje de sincronización total"></progress>
                    <p id="syncMiniEstado" role="status" aria-live="polite">Consultando los trabajos guardados…</p>
                    <?php if(is_array($mensajeSyncDashboard)):?><p class="sync-mini-mensaje"><?php echo htmlspecialchars((string)($mensajeSyncDashboard['texto']??''),ENT_QUOTES,'UTF-8'); ?></p><?php endif;?>
                    <div class="sync-dashboard-mini-acciones">
                        <form method="post" action="<?php echo htmlspecialchars(asesconUrl('controladores/sincronizacion/ControladorSincronizacionGeneral.php'),ENT_QUOTES,'UTF-8'); ?>" onsubmit="return confirm('¿Solicitar los períodos pendientes? Los meses históricos completos se conservarán.');">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(asesconCsrf('csrf_sync_general'),ENT_QUOTES,'UTF-8'); ?>">
                            <input type="hidden" name="origen" value="dashboard">
                            <button type="submit" id="syncMiniSolicitar">Solicitar sincronización</button>
                        </form>
                        <a class="sync-mini-ver" href="utilitarios/sincronizacion.php#syncPanel">Ver más</a>
                    </div>
                </section>
                <?php endif;?>
                <div class="date"><span>▣</span><?php echo htmlspecialchars($fechaTexto); ?></div>
            </div>

            <div class="cards">
                <div class="card risk">
                    <div class="card-title"><div class="card-icon red">⚠</div><span>RIESGO ALTO</span></div>
                    <div class="card-number"><?php echo $riesgoAlto; ?></div>
                    <p>Clientes en riesgo de multa</p>
                    <a href="vencimientos/vencimientos_sunat.php" class="card-button">Ver riesgos</a>
                </div>
                <div class="card">
                    <div class="card-title"><div class="card-icon orange">▣</div><span>VENCE HOY</span></div>
                    <div class="card-number"><?php echo $venceHoySunat + $venceHoyTareas; ?></div>
                    <p><?php echo $venceHoySunat; ?> SUNAT, <?php echo $venceHoyTareas; ?> tareas</p>
                    <a href="vencimientos/vencimientos_sunat.php" class="card-button">Ver vencimientos</a>
                </div>
                <div class="card">
                    <div class="card-title"><div class="card-icon yellow">◷</div><span>POR VENCER (7D)</span></div>
                    <div class="card-number"><?php echo $proximos7; ?></div>
                    <p>Obligaciones próximas</p>
                    <a href="vencimientos/vencimientos_sunat.php" class="card-button">Ver próximos</a>
                </div>
                <div class="card">
                    <div class="card-title"><div class="card-icon green">✓</div><span>AL DÍA</span></div>
                    <div class="card-number"><?php echo $porcentajeAlDia; ?>%</div>
                    <p><?php echo $clientesAlDia; ?> de <?php echo $totalClientes; ?> clientes</p>
                    <a href="clientes/gestion_clientes.php" class="card-button">Ver clientes</a>
                </div>
                <div class="card">
                    <div class="card-title"><div class="card-icon red">✉</div><span>NOTIFICACIONES</span></div>
                    <div class="card-number"><?php echo $sinLeer; ?></div>
                    <p>En Buzón SOL - por revisar</p>
                    <a href="buzones/buzon_sol_sunat.php" class="card-button">Revisar ahora</a>
                </div>
            </div>

        <section class="panel-riesgo" aria-labelledby="tituloRiesgoTributario">
            <div class="panel-riesgo-header">
                <div class="panel-riesgo-heading">
                    <span class="panel-riesgo-kicker"><span class="python-dot">PY</span> Análisis tributario</span>
                    <h2 id="tituloRiesgoTributario">Riesgo tributario de clientes</h2>
                    <p>Priorización automática generada por Python según obligaciones vencidas y pendientes.</p>
                </div>

                <div class="fecha-riesgo">
                    <span>Último análisis</span>
                    <strong><?php echo htmlspecialchars($riesgoData["fecha_analisis"] ?? "Sin análisis"); ?></strong>
                </div>
            </div>

            <div class="riesgo-resumen-grid">
                <div class="riesgo-resumen-card total">
                    <span>Clientes analizados</span>
                    <strong><?php echo count($clientesRiesgo); ?></strong>
                    <small>resultado del análisis actual</small>
                </div>
                <div class="riesgo-resumen-card alto">
                    <span>Riesgo alto</span>
                    <strong><?php echo $totalRiesgoAlto; ?></strong>
                    <small>requiere atención prioritaria</small>
                </div>
                <div class="riesgo-resumen-card medio">
                    <span>Riesgo medio</span>
                    <strong><?php echo $totalRiesgoMedio; ?></strong>
                    <small>conviene hacer seguimiento</small>
                </div>
                <div class="riesgo-resumen-card bajo">
                    <span>Riesgo bajo</span>
                    <strong><?php echo $totalRiesgoBajo; ?></strong>
                    <small>sin alertas críticas</small>
                </div>
            </div>

            <div class="tabla-riesgo">
                <table>
                    <thead>
                        <tr>
                            <th>RUC</th>
                            <th>Cliente</th>
                            <th>Vencidas</th>
                            <th>Pendientes</th>
                            <th>Puntaje</th>
                            <th>Riesgo</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($clientesRiesgo)): ?>
                        <tr>
                            <td colspan="6"><div class="riesgo-vacio">No existen resultados de análisis todavía.</div></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($clientesRiesgo as $clienteRiesgo): ?>
                            <?php
                            $nivel = strtoupper($clienteRiesgo["nivel"] ?? "BAJO");
                            $claseRiesgo = strtolower($nivel);
                            ?>
                            <tr>
                                <td><strong class="riesgo-ruc"><?php echo htmlspecialchars($clienteRiesgo["ruc"] ?? ""); ?></strong></td>
                                <td><?php echo htmlspecialchars($clienteRiesgo["razon_social"] ?? ""); ?></td>
                                <td><?php echo (int)($clienteRiesgo["obligaciones_vencidas"] ?? 0); ?></td>
                                <td><?php echo (int)($clienteRiesgo["obligaciones_pendientes"] ?? 0); ?></td>
                                <td><span class="riesgo-puntaje"><?php echo (int)($clienteRiesgo["puntaje"] ?? 0); ?></span></td>
                                <td><span class="badge-riesgo <?php echo $claseRiesgo; ?>"><?php echo htmlspecialchars($nivel); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

            <div class="reference-events">
                <strong>Eventos del mes</strong>
                <?php if($proximos===[]): ?>
                    <span class="reference-event">Sin eventos próximos registrados</span>
                <?php else: foreach(array_slice($proximos,0,3) as $ev): ?>
                    <a class="reference-event" href="vencimientos/vencimientos_sunat.php"><?php echo htmlspecialchars($ev['tipo_obligacion']); ?><span><?php echo htmlspecialchars(date('d M',strtotime($ev['fecha_vencimiento']))); ?></span></a>
                <?php endforeach; endif; ?>
            </div>

            <div class="reference-two-col" style="margin-bottom:18px">
                <section class="panel">
                    <div class="panel-header"><div class="panel-title"><span class="panel-icon">☷</span><h2>Prioridades de hoy</h2></div><a class="view-all" href="tareas/tareas.php">Ver todas</a></div>
                    <div class="reference-priority-list">
                    <?php if($prioridadesHoy===[]): ?>
                        <div class="reference-empty" style="border-top:0;padding:28px 10px">No hay tareas prioritarias para hoy.</div>
                    <?php else: foreach($prioridadesHoy as $t): ?>
                        <div class="reference-priority-item"><span class="marker"></span><div><strong><?php echo htmlspecialchars($t['titulo']); ?></strong><small><?php echo htmlspecialchars((string)($t['razon_social']?:'Tarea interna')); ?> · <?php echo htmlspecialchars($t['prioridad']); ?></small>
                            <div class="dashboard-task-actions"><a class="mini-btn" href="tareas/tareas.php?q=<?php echo rawurlencode((string)$t['titulo']); ?>">Ver / editar</a>
                            <?php if($puedeEliminarTareasDashboard):?>
                            <form method="post" action="../controladores/tareas/ControladorTarea.php" onsubmit="return confirm('¿Eliminar definitivamente esta tarea?');">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfTareasDashboard,ENT_QUOTES,'UTF-8'); ?>">
                            <input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id_tarea" value="<?php echo (int)$t['id_tarea']; ?>">
                            <button type="submit" class="mini-btn task-delete-btn">🗑 Eliminar</button></form>
                            <?php endif;?></div>
                        </div></div>
                    <?php endforeach; endif; ?>
                    </div>
                </section>
                <section class="panel">
                    <div class="panel-header"><div class="panel-title"><span class="panel-icon">⌁</span><h2>Estado tributario</h2></div></div>
                    <div class="reference-gauge" style="--gauge-angle:<?php echo max(0,min(180,$porcentajeAlDia*1.8)); ?>deg"><div class="arc"></div><strong><?php echo $porcentajeAlDia; ?>%</strong><small><?php echo $clientesAlDia; ?> de <?php echo $totalClientes; ?> clientes al día</small></div>
                    <div class="reference-chip-row" style="justify-content:center"><span class="reference-badge danger">Riesgo <?php echo $riesgoAlto; ?></span><span class="reference-badge warning">Vence hoy <?php echo $venceHoySunat+$venceHoyTareas; ?></span><span class="reference-badge success">Al día <?php echo $clientesAlDia; ?></span></div>
                </section>
            </div>

            <div class="dashboard-grid">
                <section class="panel deadlines">
                    <div class="panel-header">
                        <div class="panel-title"><span class="panel-icon">▣</span><h2>Próximos vencimientos</h2></div>
                        <a href="vencimientos/vencimientos_sunat.php" class="view-all">Ver todos</a>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead><tr><th>Cliente</th><th>Obligación</th><th>Periodo</th><th>Vencimiento</th><th>Días</th><th>Estado</th></tr></thead>
                            <tbody>
                            <?php if ($proximos === []): ?>
                                <tr><td colspan="6">No hay vencimientos registrados para tus empresas.</td></tr>
                            <?php else: foreach ($proximos as $v):
                                $diasRest = (int)$v['dias'];
                                $vencido = $diasRest < 0;
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($v['razon_social']); ?></td>
                                    <td><?php echo htmlspecialchars($v['tipo_obligacion']); ?></td>
                                    <td><?php echo htmlspecialchars($v['periodo']); ?></td>
                                    <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($v['fecha_vencimiento']))); ?></td>
                                    <td><?php echo $diasRest; ?></td>
                                    <td><span class="status <?php echo $vencido ? 'expired' : 'pending'; ?>"><?php echo $vencido ? 'Vencido' : 'Por vencer'; ?></span></td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="panel-footer"><a href="vencimientos/vencimientos_sunat.php">Ver todos los vencimientos</a></div>
                </section>

                <section class="panel alerts">
                    <div class="panel-header"><div class="panel-title"><span class="panel-icon">⚠</span><h2>Alertas recientes</h2></div></div>
                    <div class="alerts-list">
                    <?php if ($alertas === []): ?>
                        <div class="alert-item"><div class="alert-icon green">✓</div><div class="alert-text">No hay alertas pendientes registradas.</div><span class="alert-time">Ahora</span></div>
                    <?php else: foreach ($alertas as $a):
                        $nivel = strtoupper((string)$a['nivel']);
                        $clase = in_array($nivel,['ALTA','CRITICA'],true) ? 'red' : ($nivel==='MEDIA' ? 'orange' : 'blue');
                    ?>
                        <div class="alert-item">
                            <div class="alert-icon <?php echo $clase; ?>">!</div>
                            <div class="alert-text"><?php echo htmlspecialchars($a['titulo']); ?></div>
                            <span class="alert-time"><?php echo htmlspecialchars(date('d/m H:i', strtotime($a['fecha_alerta']))); ?></span>
                        </div>
                    <?php endforeach; endif; ?>
                    </div>
                    <div class="panel-footer"><a href="alertas/alertas_sunat.php">Ver todas las alertas</a></div>
                </section>
            </div>

            <footer>© <?= (int)(new DateTimeImmutable("now", new DateTimeZone("America/Lima")))->format("Y") ?> ASESCON - Todos los derechos reservados.</footer>
        </section>
    </main>
</div>
<script src="../publico/js/navegacion/menu_lateral.js"></script><script src="../publico/js/navegacion/busqueda_clientes.js?v=20261014_1"></script><script src="../publico/js/navegacion/menu_movil.js"></script><script src="../publico/js/interfaz/componentes_interfaz.js?v=20260915_3"></script>
<script src="../publico/js/interfaz/tablas_adaptables.js?v=<?php echo (int)@filemtime(__DIR__.'/../publico/js/interfaz/tablas_adaptables.js'); ?>"></script>
<script src="../publico/js/sincronizacion/progreso_dashboard.js?v=<?php echo (int)@filemtime(__DIR__.'/../publico/js/sincronizacion/progreso_dashboard.js'); ?>" defer></script></body>
</html>
