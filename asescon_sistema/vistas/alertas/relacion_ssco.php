<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../controladores/sunat/ControladorPadronSsco.php';

$moduloActivo = 'RELACION_SSCO';
$sscoVista = (new ControladorPadronSsco($conexion))->datos($_GET);
$esc = static fn(string $recurso): string => htmlspecialchars(asesconRecurso($recurso), ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Relación SSCO - ASESCON</title>
    <link rel="stylesheet" href="<?= $esc('publico/css/base/estructura_panel.css') ?>">
    <link rel="stylesheet" href="<?= $esc('publico/css/modulos/sunat.css') ?>">
</head>
<body>
<div class="app">
    <?php require __DIR__.'/../componentes/menu_lateral.php'; ?>
    <main class="main-content">
        <?php require __DIR__.'/../componentes/barra_superior.php'; ?>
        <section class="content">
            <div class="page-header"><h1>Relación SSCO</h1></div>
            <?php require __DIR__.'/componentes/padron_ssco.php'; ?>
            <footer>© <?= date('Y') ?> ASESCON - Todos los derechos reservados.</footer>
        </section>
    </main>
</div>
<script src="<?= $esc('publico/js/navegacion/menu_lateral.js') ?>" defer></script>
<script src="<?= $esc('publico/js/navegacion/busqueda_clientes.js') ?>" defer></script>
<script src="<?= $esc('publico/js/navegacion/menu_movil.js') ?>" defer></script>
<script src="<?= $esc('publico/js/interfaz/componentes_interfaz.js') ?>" defer></script>
<script src="<?= $esc('publico/js/interfaz/tablas_adaptables.js') ?>" defer></script>
</body>
</html>
