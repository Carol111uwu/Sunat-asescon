<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
asesconExigirLogin();
$grupoClientes='SERVICIOS_CLIENTES';
require __DIR__.'/componentes/pagina_secciones_clientes.php';
