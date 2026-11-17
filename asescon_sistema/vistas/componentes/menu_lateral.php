<?php

if (!isset($menuLateral)) {
    require __DIR__ . '/../../controladores/interfaz/preparar_menu_lateral.php';
}
if (!isset($menuLateral)) {
    return;
}
$escMenu = static fn(string $valor): string => htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');

$pintarMenu = static function (array $nodos, bool $raiz = false) use (&$pintarMenu, $escMenu): void {
    foreach ($nodos as $nodo) {
        $activo = $nodo['activo'];
        $texto = $escMenu($nodo['texto']);
        $icono = $escMenu($nodo['icono']);
        if (isset($nodo['elementos'])) {
            $id = $escMenu($nodo['id']); ?>
            <div class="menu-group">
                <button class="menu-item dropdown-btn<?= $raiz ? '' : ' submenu-toggle sire-toggle' ?><?= $activo ? '' : ' closed' ?>"
                        type="button" data-target="<?= $id ?>" aria-controls="<?= $id ?>" aria-expanded="<?= $activo ? 'true' : 'false' ?>">
                    <span class="<?= $raiz ? 'icon' : 'sub-icon' ?>" aria-hidden="true"><?= $icono ?></span>
                    <span><?= $texto ?></span><span class="arrow" aria-hidden="true"><?= $activo ? '⌃' : '⌄' ?></span>
                </button>
                <div class="submenu<?= $raiz ? '' : ' submenu-anidado sire-submenu' ?><?= $activo ? ' open' : '' ?>" id="<?= $id ?>">
                    <?php $pintarMenu($nodo['elementos']); ?>
                </div>
            </div>
        <?php } else { ?>
            <a href="<?= $escMenu(asesconUrl($nodo['ruta'])) ?>" class="<?= trim(($raiz ? 'menu-item ' : '') . ($activo ? 'active' : '')) ?>"
               <?= $activo ? 'aria-current="page"' : '' ?>>
                <span class="<?= $raiz ? 'icon' : 'sub-icon' ?>" aria-hidden="true"><?= $icono ?></span><?= $texto ?>
                <?php if (($nodo['contador'] ?? 0) > 0): ?>
                    <span class="menu-badge"><?= $nodo['contador'] > 999 ? '999+' : $nodo['contador'] ?></span>
                <?php endif; ?>
            </a>
        <?php }
    }
};
?>
<?php if (!defined('ASESCON_TEMA_INCLUIDO')) require __DIR__ . '/recursos_tema.php'; ?>
<aside class="sidebar" id="sidebarPrincipal">
    <div class="logo-area">
        <div class="logo-icon">
            <img src="<?= $escMenu(asesconUrl('publico/img/logos/isotipo-asescon-blanco-dorado.png')) ?>" alt="ASESCON">
        </div>
        <div class="logo-text"><strong>ASESCON</strong><span>ASESORÍA CONTABLE</span></div>
    </div>

    <nav class="menu" aria-label="Menú principal">
        <?php $pintarMenu($menuLateral, true); ?>
    </nav>

    <div class="logout-area">
        <form action="<?= $escMenu(asesconUrl('vistas/cerrar_sesion.php')) ?>" method="post">
            <input type="hidden" name="csrf_token" value="<?= $escMenu(asesconCsrf('csrf_salida')) ?>">
            <button type="submit" class="logout"><span class="logout-icon" aria-hidden="true">↪</span>Cerrar sesión</button>
        </form>
        <?php require __DIR__ . '/selector_tema.php'; ?>
    </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay" hidden></div>
