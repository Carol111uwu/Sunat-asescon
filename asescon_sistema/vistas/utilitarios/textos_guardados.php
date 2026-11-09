<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../servicios/textos/CatalogoTextos.php';
asesconExigirLogin();$moduloActivo='UTILITARIOS';
$esc=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Textos guardados - ASESCON</title><link rel="stylesheet" href="<?=$esc(asesconUrl('publico/css/base/estructura_panel.css'))?>"></head><body><div class="app"><?php require __DIR__.'/../componentes/menu_lateral.php';?><main class="main-content"><?php require __DIR__.'/../componentes/barra_superior.php';?><section class="content">
<h1>Textos guardados</h1><p>Archivar oculta contenido de tu vista; restaurar lo vuelve a mostrar. <strong>Borrar solo el texto</strong> no elimina su registro contable: para borrar una tarea, plan, cotización, honorario, recordatorio o plantilla completos usa «Gestionar registro completo». Las notas internas sí pueden eliminarse por completo. Los documentos oficiales de SUNAT se conservan.</p>
<form id="filtrosTextos" class="reference-filter-grid"><label>Tipo de texto<select name="tipo"><?php foreach(CatalogoTextos::CAMPOS as $tipo=>$cfg):?><option value="<?=$esc($tipo)?>"><?=$esc(ucfirst(str_replace('_',' ',$tipo)))?></option><?php endforeach;?></select></label><label>Vista<select name="vista"><option value="activos">Activos</option><option value="archivados">Archivados</option></select></label><button class="reference-btn primary">Mostrar</button></form>
<p role="status" id="estadoTextos"></p><div id="listaTextos"></div><button type="button" class="reference-btn" id="masTextos" hidden>Cargar más</button>
</section></main></div><script src="<?=$esc(asesconUrl('publico/js/navegacion/menu_lateral.js'))?>"></script><script src="<?=$esc(asesconUrl('publico/js/textos/pagina_textos.js'))?>?v=20261028_2" defer></script></body></html>
