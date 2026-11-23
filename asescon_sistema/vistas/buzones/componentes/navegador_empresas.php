<?php
$clientesNavegables = array_values($clientes_disponibles ?? []);
$nombreNav = $buzonCodigo === 'SUNAFIL' ? 'sunafil' : 'sol';
$escNav = static fn (mixed $valor): string => htmlspecialchars((string)$valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$empresaVisible = $idCliente === 0 ? $cliente_pagina : ($clientes[$idCliente] ?? null);
$urlEmpresaNav = static fn (int $id): string => '?'.$escNav(http_build_query(
    array_replace($parametrosGuardados, ['id_cliente' => 0, 'empresa' => $id])
));
if ($empresaVisible):
?>
<section class="reference-panel buzon-navegador" aria-label="Empresa y datos guardados">
    <div class="buzon-navegador-contexto">
        <div>
            <strong><?=$escNav($empresaVisible['razon_social'])?></strong>
            <span>RUC <?=$escNav($empresaVisible['ruc'])?></span>
            <small class="buzon-fecha-exito"><?php if ($ultima_consulta_exitosa): ?>
                Última consulta completa <?=$escNav(date('d/m/Y H:i', strtotime($ultima_consulta_exitosa)))?>
            <?php else: ?>Primera consulta completa pendiente<?php endif; ?></small>
        </div>
        <?php if ($idCliente === 0): ?>
        <nav class="buzon-navegador-flechas" aria-label="Navegar entre empresas">
            <?php if ($pagina_actual > 1): ?>
            <a class="reference-btn" aria-label="Empresa anterior" href="<?=$urlEmpresaNav((int)$clientesNavegables[$pagina_actual-2]['id_cliente'])?>">‹ Anterior</a>
            <?php else: ?><button type="button" class="reference-btn" disabled>‹ Anterior</button><?php endif; ?>
            <span><?=$pagina_actual?> / <?=$total_paginas?></span>
            <?php if ($pagina_actual < $total_paginas): ?>
            <a class="reference-btn" aria-label="Empresa siguiente" href="<?=$urlEmpresaNav((int)$clientesNavegables[$pagina_actual]['id_cliente'])?>">Siguiente ›</a>
            <?php else: ?><button type="button" class="reference-btn" disabled>Siguiente ›</button><?php endif; ?>
        </nav>
        <?php endif; ?>
    </div>
    <?php if ($idCliente === 0 && $clientesNavegables): ?>
    <div class="buzon-navegador-busqueda" data-buzon-navegar
         data-clientes="<?=$escNav(json_encode(array_map(static fn (array $c): array => [
            'id'=>(int)$c['id_cliente'], 'nombre'=>$c['razon_social'], 'ruc'=>$c['ruc']
         ], $clientesNavegables), JSON_INVALID_UTF8_SUBSTITUTE))?>">
        <label for="buscarEmpresaBuzon_<?=$nombreNav?>">Buscar empresa por nombre o RUC</label>
        <div class="buzon-navegador-input">
            <input type="search" id="buscarEmpresaBuzon_<?=$nombreNav?>" list="listaEmpresaBuzon_<?=$nombreNav?>" autocomplete="off">
            <datalist id="listaEmpresaBuzon_<?=$nombreNav?>">
                <?php foreach ($clientesNavegables as $opcion): ?>
                <option value="<?=$escNav($opcion['ruc'].' · '.$opcion['razon_social'])?>"></option>
                <?php endforeach; ?>
            </datalist>
            <button type="button" class="reference-btn" data-ir-empresa-buzon>⌕ Buscar</button>
        </div>
        <small role="status" data-mensaje-navegador></small>
    </div>
    <?php endif; ?>
</section>
<?php endif; ?>
