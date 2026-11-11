<?php

require_once __DIR__ . '/../../configuracion/permisos.php';
$usuarioSidebar = asesconUsuarioActual($conexion);
$activo = strtoupper((string)($moduloActivo ?? ''));

$perm = static fn(string $codigo): bool => $codigo === 'CONFIGURACION_CORREO' ? asesconEsPrincipal($conexion) : asesconTienePermiso($conexion, $codigo === 'ENVIO_ENTREGABLES' ? 'INFORMES' : $codigo, 'ver');
$act = static fn(string $codigo): string => $activo === strtoupper($codigo) ? ' active' : '';

require_once __DIR__.'/../../configuracion/secciones_clientes.php';
$clientesItems = [['CLIENTES','♙','Gestión de clientes','vistas/clientes/gestion_clientes.php']];
foreach (asesconGruposClientes() as $codigoGrupo=>$grupoMenu) {
    if (asesconSeccionesClientesVisibles($conexion,$codigoGrupo)) {
        $clientesItems[]=[$codigoGrupo,$codigoGrupo==='COMUNICACION_CLIENTES'?'✉':'▤',$grupoMenu['titulo'],$grupoMenu['ruta']];
    }
}
$sunatItems = [
    ['GRUPO_SIRE','◫','SIRE',''],
    ['DECLARACIONES','▣','Control de declaraciones','vistas/sunat/control_declaraciones.php'],
    ['DECLARACIONES_PAGOS_SUNAT','▤','Consulta de declaraciones y pagos','vistas/sunat/declaraciones_pagos/principal.php'],
    ['DETRACCIONES','◈','Detracciones','vistas/sunat/detracciones.php'],
    ['RHE','▱','Recibos por honorarios','vistas/sunat/recibos_honorarios.php'],
    ['NO_DOMICILIADOS','▤','Comprobantes No Domiciliados','vistas/sunat/comprobantes_no_domiciliados.php'],
    ['CPE','▣','Consulta de validez CPE','vistas/sunat/consulta_cpe.php'],
    ['TIPO_CAMBIO','◌','Tipo de cambio','vistas/sunat/tipo_cambio.php'],
    ['LIQUIDACIONES','▥','Liquidación de impuestos','vistas/sunat/liquidaciones_impuestos.php'],
    ['PLAME_0601','▤','PLAME / PDT 0601','vistas/sunat/plame_0601.php'],
    ['PDT_0621','▤','PDT / Formulario 0621','vistas/sunat/pdt_0621.php'],
    ['BUZON_SOL','✉','Buzón SOL','vistas/buzones/buzon_sol_sunat.php'],
    ['ALERTAS_SUNAT','△','Alertas SUNAT','vistas/alertas/alertas_sunat.php'],
    ['VENCIMIENTOS_SUNAT','▣','Vencimientos SUNAT','vistas/vencimientos/vencimientos_sunat.php'],
];
$sireItems = [
    ['RCE','▤','RCE (Compras)','vistas/sunat/rce.php'],
    ['RVIE','▤','RVIE (Ventas)','vistas/sunat/rvie.php'],
    ['SIRE','◫','Dashboard SIRE','vistas/sunat/panel_sire.php'],
    ['COMPROBANTES_SUNAT','▤','Comprobantes SUNAT','vistas/sunat/comprobantes_sunat.php'],
];
$sunafilItems = [
    ['BUZON_SUNAFIL','✉','Buzón SUNAFIL','vistas/buzones/buzon_sunafil.php'],
];
$afpItems = [
    ['AFP','◫','Declaraciones AFP','vistas/afp/afp.php'],
];
$utilItems = [
    ['UTILITARIOS','⚙','Panel de utilitarios','vistas/utilitarios/utilitarios.php'],
    ['UTIL_RUTA','⌁','Ruta de declaraciones','vistas/utilitarios/ruta_declaraciones.php'],
    ['UTIL_WHATSAPP','◉','Notificaciones WhatsApp','vistas/utilitarios/notificaciones_whatsapp.php'],
];
if (asesconEsPrincipal($conexion)) { $utilItems[] = ['CONFIGURACION_CORREO','✉','Configuración de correo','vistas/comunicaciones/configuracion_correo.php']; }
$clientesVisibles = array_values(array_filter($clientesItems, fn($i) => in_array($i[0],['COMUNICACION_CLIENTES','SERVICIOS_CLIENTES'],true) || $perm($i[0])));
$sireVisibles = array_values(array_filter($sireItems, fn($i) => $perm($i[0])));
$sunatVisibles = array_values(array_filter($sunatItems, fn($i) => $i[0] === 'GRUPO_SIRE' ? $sireVisibles !== [] : $perm($i[0])));
$sunafilVisibles = array_values(array_filter($sunafilItems, fn($i) => $perm($i[0])));
$afpVisibles = array_values(array_filter($afpItems, fn($i) => $perm($i[0])));
$utilVisibles = $perm('UTILITARIOS') ? $utilItems : [];

$sireCodigos = array_column($sireItems, 0);
$sunatCodigos = array_merge(array_column($sunatItems, 0), $sireCodigos);
$sunafilCodigos = array_column($sunafilItems, 0);
$afpCodigos = array_column($afpItems, 0);
$clientesCodigos = array_column($clientesItems, 0);
$utilCodigos = array_column($utilItems, 0);
$sunatAbierto = in_array($activo, $sunatCodigos, true);
$sireAbierto = in_array($activo, $sireCodigos, true);
$sunafilAbierto = in_array($activo, $sunafilCodigos, true);
$afpAbierto = in_array($activo, $afpCodigos, true);
$clientesAbierto = in_array($activo, $clientesCodigos, true);
$utilAbierto = in_array($activo, $utilCodigos, true);

require_once __DIR__ . '/../../controladores/interfaz/ControladorIndicadoresMenu.php';
$indicadoresMenu = (new ControladorIndicadoresMenu($conexion))->obtener();
$badgeSol = $indicadoresMenu['sol'];
$badgeSunafil = $indicadoresMenu['sunafil'];

?>
<aside class="sidebar" id="sidebarPrincipal">
    <div class="logo-area">
        <div class="logo-icon">
            <img
                src="<?php echo htmlspecialchars(asesconUrl('publico/img/logos/isotipo-asescon-blanco-dorado.png')); ?>"
                alt="ASESCON">
        </div>
        <div class="logo-text"><strong>ASESCON</strong><span>ASESORÍA CONTABLE</span></div>
    </div>

    <nav class="menu">
        <?php if ($perm('DASHBOARD')): ?>
        <a href="<?php echo htmlspecialchars(asesconUrl('vistas/panel_principal.php')); ?>" class="menu-item<?php echo $act('DASHBOARD'); ?>">
            <span class="icon">⌂</span><span>Dashboard</span>
        </a>
        <?php endif; ?>

        <?php if ($clientesVisibles !== []): ?>
        <div class="menu-group">
            <button class="menu-item dropdown-btn<?php echo $clientesAbierto ? '' : ' closed'; ?>" type="button" data-target="clientesMenu">
                <span class="icon">▥</span><span>Mis clientes</span><span class="arrow"><?php echo $clientesAbierto ? '⌃' : '⌄'; ?></span>
            </button>
            <div class="submenu<?php echo $clientesAbierto ? ' open' : ''; ?>" id="clientesMenu">
                <?php foreach ($clientesVisibles as [$codigo,$icono,$texto,$ruta]): ?>
                    <a href="<?php echo htmlspecialchars(asesconUrl($ruta)); ?>"<?php echo $activo === $codigo ? ' class="active"' : ''; ?>>
                        <span class="sub-icon"><?php echo $icono; ?></span><?php echo htmlspecialchars($texto); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($perm('TAREAS')): ?>
        <a href="<?php echo htmlspecialchars(asesconUrl('vistas/tareas/tareas.php')); ?>" class="menu-item<?php echo $act('TAREAS'); ?>">
            <span class="icon">☷</span><span>Tareas</span>
        </a>
        <?php endif; ?>

        <?php if ($perm('EQUIPO')): ?>
        <a href="<?php echo htmlspecialchars(asesconUrl('vistas/equipo/equipo.php')); ?>" class="menu-item<?php echo $act('EQUIPO'); ?>">
            <span class="icon">♙</span><span>Mi equipo</span>
        </a>
        <?php endif; ?>

        <?php if ($sunatVisibles !== []): ?>
        <div class="menu-group">
            <button class="menu-item dropdown-btn<?php echo $sunatAbierto ? '' : ' closed'; ?>" type="button" data-target="sunatMenu">
                <span class="icon">◉</span><span>SUNAT</span><span class="arrow"><?php echo $sunatAbierto ? '⌃' : '⌄'; ?></span>
            </button>
            <div class="submenu<?php echo $sunatAbierto ? ' open' : ''; ?>" id="sunatMenu">
                <?php foreach ($sunatVisibles as [$codigo,$icono,$texto,$ruta]): ?>
                    <?php if ($codigo === 'GRUPO_SIRE'): ?>
                    <div class="sire-subgroup">
                        <button class="menu-item sire-toggle dropdown-btn<?php echo $sireAbierto ? '' : ' closed'; ?>" type="button" data-target="sireMenu" aria-controls="sireMenu" aria-expanded="<?php echo $sireAbierto ? 'true' : 'false'; ?>">
                            <span class="sub-icon">◫</span><span>SIRE</span><span class="arrow"><?php echo $sireAbierto ? '⌃' : '⌄'; ?></span>
                        </button>
                        <div class="submenu sire-submenu<?php echo $sireAbierto ? ' open' : ''; ?>" id="sireMenu">
                            <?php foreach ($sireVisibles as [$sireCodigo,$sireIcono,$sireTexto,$sireRuta]): ?>
                            <a href="<?php echo htmlspecialchars(asesconUrl($sireRuta)); ?>"<?php echo $activo === $sireCodigo ? ' class="active"' : ''; ?>>
                                <span class="sub-icon"><?php echo $sireIcono; ?></span><?php echo htmlspecialchars($sireTexto); ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <a href="<?php echo htmlspecialchars(asesconUrl($ruta)); ?>"<?php echo $activo === $codigo ? ' class="active"' : ''; ?>>
                        <span class="sub-icon"><?php echo $icono; ?></span><?php echo htmlspecialchars($texto); ?>
                        <?php if($codigo==='BUZON_SOL' && $badgeSol>0): ?><span class="menu-badge"><?php echo $badgeSol>999?'999+':$badgeSol; ?></span><?php endif; ?>
                    </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="menu-separator"></div>

    <?php if ($afpVisibles !== []): ?>
        <div class="menu-group">
            <button
                class="menu-item dropdown-btn<?php echo $afpAbierto ? '' : ' closed'; ?>"
                type="button"
                data-target="afpMenu">

                <span class="icon">◫</span>
                <span>AFP</span>
                <span class="arrow"><?php echo $afpAbierto ? '⌃' : '⌄'; ?></span>

            </button>

            <div class="submenu<?php echo $afpAbierto ? ' open' : ''; ?>" id="afpMenu">
                
                <?php foreach ($afpVisibles as [$codigo,$icono,$texto,$ruta]): ?>
                    
                    <a
                        href="<?php echo htmlspecialchars(asesconUrl($ruta)); ?>">

                        <span class="sub-icon"><?php echo $icono; ?></span>
                        <?php echo htmlspecialchars($texto); ?>

                    </a>

                <?php endforeach; ?>

             </div>

        </div>

    <?php endif; ?>

        <?php if ($sunafilVisibles !== []): ?>
        <div class="menu-group">
            <button class="menu-item dropdown-btn<?php echo $sunafilAbierto ? '' : ' closed'; ?>" type="button" data-target="sunafilMenu">
                <span class="icon">▣</span><span>SUNAFIL</span><span class="arrow"><?php echo $sunafilAbierto ? '⌃' : '⌄'; ?></span>
            </button>
            <div class="submenu<?php echo $sunafilAbierto ? ' open' : ''; ?>" id="sunafilMenu">
                <?php foreach ($sunafilVisibles as [$codigo,$icono,$texto,$ruta]): ?>
                    <a href="<?php echo htmlspecialchars(asesconUrl($ruta)); ?>"<?php echo $activo === $codigo ? ' class="active"' : ''; ?>>
                        <span class="sub-icon"><?php echo $icono; ?></span><?php echo htmlspecialchars($texto); ?>
                        <?php if($codigo==='BUZON_SUNAFIL' && $badgeSunafil>0): ?><span class="menu-badge"><?php echo $badgeSunafil>999?'999+':$badgeSunafil; ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($utilVisibles !== []): ?>
        <div class="menu-group">
            <button class="menu-item dropdown-btn<?php echo $utilAbierto ? '' : ' closed'; ?>" type="button" data-target="utilMenu">
                <span class="icon">⚙</span><span>Utilitarios</span><span class="arrow"><?php echo $utilAbierto ? '⌃' : '⌄'; ?></span>
            </button>
            <div class="submenu<?php echo $utilAbierto ? ' open' : ''; ?>" id="utilMenu">
                <?php foreach ($utilVisibles as [$codigo,$icono,$texto,$ruta]): ?>
                    <a href="<?php echo htmlspecialchars(asesconUrl($ruta)); ?>"<?php echo $activo === $codigo ? ' class="active"' : ''; ?>>
                        <span class="sub-icon"><?php echo $icono; ?></span><?php echo htmlspecialchars($texto); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </nav>

    <div class="logout-area">
        <form action="<?php echo htmlspecialchars(asesconUrl('vistas/cerrar_sesion.php')); ?>" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(asesconCsrf('csrf_salida')); ?>">
            <button type="submit" class="logout"><span class="logout-icon">↪</span><span>Cerrar sesión</span></button>
        </form>
    </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay" hidden></div>
