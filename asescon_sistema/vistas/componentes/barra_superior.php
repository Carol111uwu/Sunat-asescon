<?php
require_once __DIR__ . '/../../controladores/interfaz/ControladorBarraSuperior.php';
$datosBarraSuperior=(new ControladorBarraSuperior($conexion))->preparar();
extract($datosBarraSuperior, EXTR_SKIP);
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(asesconUrl('publico/css/interfaz/menu_cuenta.css')); ?>?v=<?php echo (int)@filemtime(__DIR__.'/../../publico/css/interfaz/menu_cuenta.css'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(asesconUrl('publico/css/interfaz/skeleton.css')); ?>?v=20261028_1">
<link rel="stylesheet" href="<?php echo htmlspecialchars(asesconUrl('publico/css/modulos/comunicaciones.css')); ?>?v=20261014_4">
<header class="topbar">
    <div class="topbar-left">
        <button class="topbar-menu-toggle" id="menuMovilToggle" type="button" aria-label="Abrir menú de navegación" aria-expanded="false" aria-controls="sidebarPrincipal">
            <span aria-hidden="true">☰</span>
        </button>
        <button class="topbar-search-trigger" id="busquedaRapidaToggle" type="button" aria-label="Abrir búsqueda rápida" title="Buscar cliente por RUC, nombre, teléfono o correo">
            <span class="search-icon">⌕</span><span class="search-copy">Buscar cliente o RUC...</span>
        </button>
        <div class="quick-search" id="quickSearch" hidden>
            <div class="quick-search-box">
                <div class="quick-search-head"><strong>Buscar cliente</strong><button type="button" id="quickSearchClose" aria-label="Cerrar">×</button></div>
                <input id="quickSearchInput" type="search" autocomplete="off" placeholder="RUC, razón social, nombre comercial, celular o correo...">
                <div class="quick-search-hint">Selecciona una empresa de la lista o escribe su nombre/RUC. Solo aparecen las empresas permitidas para tu cuenta.</div>
                <div id="quickSearchResults" class="quick-search-results"><div class="quick-search-empty">Cargando empresas permitidas…</div></div>
            </div>
        </div>
    </div>
    <div class="topbar-right">
        <?php require __DIR__.'/consultas_oficiales.php'; ?>
        <?php require __DIR__.'/avisos_ssco.php'; ?>
        <?php require __DIR__.'/avisos_casos_sol.php'; ?>

        <div class="account-menu-wrap" data-account-menu>
            <button class="user-area" type="button" data-account-menu-toggle aria-expanded="false" aria-haspopup="true">
                <div class="avatar"><?php echo htmlspecialchars(strtoupper(substr($usuarioMostrar,0,1))); ?></div>
                <span><?php echo htmlspecialchars($usuarioMostrar); ?></span>
                <span class="user-arrow">⌄</span>
            </button>
            <div class="account-menu-panel" data-account-menu-panel hidden>
                <div class="account-menu-title">Cuentas</div>
                <div class="account-menu-list">
                    <?php foreach ($cuentasMenu as $cuenta): ?>
                        <?php if ((bool)$cuenta['actual']): ?>
                            <div class="account-menu-item current" aria-current="true">
                                <div class="account-menu-avatar"><?php echo htmlspecialchars(strtoupper(substr((string)$cuenta['usuario'],0,1))); ?></div>
                                <div class="account-menu-copy">
                                    <strong><?php echo htmlspecialchars((string)$cuenta['usuario']); ?></strong>
                                    <span><?php echo htmlspecialchars((string)$cuenta['rol_nombre']); ?></span>
                                </div>
                            </div>
                        <?php elseif ((bool)$cuenta['puede_cambiar']): ?>
                            <form class="account-menu-switch-form" method="post" action="<?php echo htmlspecialchars(asesconUrl('controladores/autenticacion/ControladorCambioCuenta.php')); ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfCambiarCuenta, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="id_usuario" value="<?php echo (int)$cuenta['id_usuario']; ?>">
                                <button class="account-menu-item account-menu-switch" type="submit" title="Cambiar a <?php echo htmlspecialchars((string)$cuenta['usuario']); ?>">
                                    <div class="account-menu-avatar"><?php echo htmlspecialchars(strtoupper(substr((string)$cuenta['usuario'],0,1))); ?></div>
                                    <div class="account-menu-copy">
                                        <strong><?php echo htmlspecialchars((string)$cuenta['usuario']); ?></strong>
                                        <span><?php echo htmlspecialchars((string)$cuenta['rol_nombre']); ?></span>
                                    </div>
                                    <span class="account-menu-switch-indicator" aria-hidden="true">›</span>
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php if (asesconEsPrincipal($conexion)): ?>
                <div class="account-menu-footer"><a href="<?php echo htmlspecialchars(asesconUrl('vistas/comunicaciones/configuracion_correo.php')); ?>">Configuración de correo</a></div>
                <?php endif; ?>
                <?php if (asesconPuedeAutogestionarCredenciales($conexion)): ?>
                <div class="account-menu-footer">
                    <a href="<?php echo htmlspecialchars(asesconUrl('vistas/seguridad/mi_perfil.php')); ?>">Configuración de mi perfil</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <script defer src="<?php echo htmlspecialchars(asesconUrl('publico/js/interfaz/menu_cuenta.js')); ?>?v=<?php echo (int)@filemtime(__DIR__.'/../../publico/js/interfaz/menu_cuenta.js'); ?>"></script>
    </div>
</header>
<script defer src="<?php echo htmlspecialchars(asesconUrl('publico/js/navegacion/consultas_oficiales.js')); ?>?v=20261014_4"></script>

<link rel="stylesheet" href="<?php echo htmlspecialchars(asesconUrl('publico/css/interfaz/autocompletar_clientes.css')); ?>?v=20261014_4">
<link rel="stylesheet" href="<?php echo htmlspecialchars(asesconUrl('publico/css/interfaz/selector_clientes.css')); ?>?v=<?php echo (int)@filemtime(__DIR__.'/../../publico/css/interfaz/selector_clientes.css'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(asesconUrl('publico/css/interfaz/tipografia_global.css')); ?>?v=<?php echo (int)@filemtime(__DIR__.'/../../publico/css/interfaz/tipografia_global.css'); ?>">
<script defer src="<?php echo htmlspecialchars(asesconUrl('publico/js/clientes/autocompletar_clientes.js')); ?>?v=20261014_4" data-url="<?php echo htmlspecialchars(asesconUrl('api/clientes/buscar_clientes.php')); ?>"></script>
<script defer src="<?php echo htmlspecialchars(asesconUrl('publico/js/clientes/selector_clientes.js')); ?>?v=<?php echo (int)@filemtime(__DIR__.'/../../publico/js/clientes/selector_clientes.js'); ?>"></script>
<script defer src="<?php echo htmlspecialchars(asesconUrl('publico/js/interfaz/periodos_2021_actual.js')); ?>?v=<?php echo (int)@filemtime(__DIR__.'/../../publico/js/interfaz/periodos_2021_actual.js'); ?>"></script>

<script defer src="<?=htmlspecialchars(asesconUrl('publico/js/textos/gestion_textos.js'))?>?v=20261028_2" data-api="<?=htmlspecialchars(asesconUrl('api/textos/gestionar.php'))?>" data-csrf="<?=htmlspecialchars(asesconCsrf('csrf_textos'),ENT_QUOTES,'UTF-8')?>" data-archivo="<?=htmlspecialchars(asesconUrl('vistas/utilitarios/textos_guardados.php'))?>"></script>
<style>.texto-acciones{display:flex;flex-wrap:wrap;gap:6px;margin:6px 0}.texto-acciones button{cursor:pointer}[data-texto-tipo][hidden]{display:none!important}</style>
