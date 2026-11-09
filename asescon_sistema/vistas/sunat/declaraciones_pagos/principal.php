<?php
declare(strict_types=1);
require_once __DIR__.'/../../../configuracion/inicializacion.php';
require_once __DIR__.'/../../../controladores/sunat/declaraciones_pagos/ControladorConsultaDeclaraciones.php';
asesconExigirPermiso($conexion,'DECLARACIONES_PAGOS_SUNAT','ver');
$moduloActivo='DECLARACIONES_PAGOS_SUNAT';
$datosModulo=(new ControladorConsultaDeclaraciones($conexion))->datosVista();
extract($datosModulo,EXTR_SKIP);
$csrfOficial=asesconCsrf('csrf_consulta_oficial');
$seccionOficial=(string)($_GET['seccion']??'consulta');
if(!in_array($seccionOficial,['consulta','constancias','casillas','reportes'],true))$seccionOficial='consulta';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Consulta de declaraciones y pagos SUNAT - ASESCON</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(asesconUrl('publico/css/base/estructura_panel.css')); ?>?v=20261013_2">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(asesconUrl('publico/css/modulos/sunat.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(asesconUrl('publico/css/modulos/declaraciones_pagos/modulo.css')); ?>?v=20261015_2">
</head>
<body><div class="app">
<?php require __DIR__.'/../../componentes/menu_lateral.php'; ?>
<main class="main-content">
    <?php require __DIR__.'/../../componentes/barra_superior.php'; ?>
    <section class="content">
        <div class="reference-head-actions"><div class="page-header"><div>
            <h1>Consulta de declaraciones y pagos SUNAT</h1>
            <p>Consulta de información disponible por empresa; este módulo no presenta declaraciones ni ejecuta pagos bancarios.</p>
        </div></div></div>
        <?php require __DIR__.'/componentes/panel_consulta.php'; ?>
        <?php require __DIR__.'/componentes/accesos_relacionados.php'; ?>
        <footer>© <?= (int)(new DateTimeImmutable("now", new DateTimeZone("America/Lima")))->format("Y") ?> ASESCON - Todos los derechos reservados.</footer>
    </section>
</main></div>
<script src="<?php echo htmlspecialchars(asesconUrl('publico/js/navegacion/menu_lateral.js')); ?>"></script>
<script src="<?php echo htmlspecialchars(asesconUrl('publico/js/navegacion/busqueda_clientes.js')); ?>?v=20261014_1"></script>
<script src="<?php echo htmlspecialchars(asesconUrl('publico/js/navegacion/menu_movil.js')); ?>"></script>
<script src="<?php echo htmlspecialchars(asesconUrl('publico/js/interfaz/componentes_interfaz.js')); ?>?v=20260915_3"></script>
<script src="<?php echo htmlspecialchars(asesconUrl('publico/js/interfaz/tablas_adaptables.js')); ?>?v=20261015_1"></script>
<script type="module" src="<?php echo htmlspecialchars(asesconUrl('publico/js/sunat/declaraciones_pagos/modulo.js')); ?>?v=<?= (int)@filemtime(__DIR__ . '/../../../publico/js/sunat/declaraciones_pagos/modulo.js') ?>"></script>
</body></html>
