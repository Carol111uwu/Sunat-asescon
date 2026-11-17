<?php
define('ASESCON_TEMA_INCLUIDO', true);
$escTema = static fn(string $ruta): string => htmlspecialchars(asesconRecurso($ruta), ENT_QUOTES, 'UTF-8');
?>
<link rel="stylesheet" href="<?= $escTema('publico/css/base/tema.css') ?>">
<script src="<?= $escTema('publico/js/interfaz/tema.js') ?>"></script>
