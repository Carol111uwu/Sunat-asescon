<?php
declare(strict_types=1);require_once __DIR__.'/../../configuracion/inicializacion.php';asesconExigirPermiso($conexion,'UTILITARIOS','ver');$moduloActivo='UTILITARIOS';
$herramientas=[
 ['⌁','Ruta de declaraciones','Guía visual de pasos para declaraciones y revisiones.','ruta_declaraciones.php'],
 ['◉','Notificaciones WhatsApp','Acceso a recordatorios y flujo de mensajería.','notificaciones_whatsapp.php'],
 ['▤','Textos guardados','Consulta tus textos archivados sin ocupar espacio en la barra superior.','textos_guardados.php'],
];
if (asesconEsPrincipal($conexion)) {
 $herramientas[]=['✉','Configuración de correo','Remitente, plantillas, avisos y catálogo de entregables.','../comunicaciones/configuracion_correo.php'];
 $herramientas[]=['◷','Sincronización total','Consulta el avance de ventas, compras, declaraciones, padrones y buzones.','sincronizacion.php'];
}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Utilitarios - ASESCON</title><link rel="stylesheet" href="../../publico/css/base/estructura_panel.css?v=20261028_ui2"><link rel="stylesheet" href="../../publico/css/modulos/sunat.css"></head><body><div class="app"><?php require __DIR__.'/../componentes/menu_lateral.php';?><main class="main-content"><?php require __DIR__.'/../componentes/barra_superior.php';?><section class="content"><div class="page-header"><div><h1>⚙ Utilitarios</h1><p>Herramientas complementarias reunidas en un solo panel, sin mezclar su lógica con SUNAT.</p></div></div><div class="utility-grid"><?php foreach($herramientas as [$ico,$titulo,$texto,$ruta]):?><a class="utility-card" href="<?php echo htmlspecialchars($ruta);?>"><span style="font-size:26px"><?php echo $ico;?></span><strong><?php echo htmlspecialchars($titulo);?></strong><p><?php echo htmlspecialchars($texto);?></p><span class="reference-btn">Abrir</span></a><?php endforeach;?></div><footer>© <?= (int)(new DateTimeImmutable("now", new DateTimeZone("America/Lima")))->format("Y") ?> ASESCON - Todos los derechos reservados.</footer></section></main></div><script src="../../publico/js/navegacion/menu_lateral.js"></script><script src="../../publico/js/navegacion/busqueda_clientes.js?v=20261014_1"></script><script src="../../publico/js/navegacion/menu_movil.js"></script><script src="../../publico/js/interfaz/componentes_interfaz.js?v=20260915_3"></script></body></html>
