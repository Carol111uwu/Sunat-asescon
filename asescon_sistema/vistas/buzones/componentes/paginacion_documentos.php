<?php if ($paginas_documentos > 1):
    $urlPaginaDocumentos = static function (int $destino) use ($parametrosGuardados, $idCliente): string {
        $parametro = $idCliente === 0 ? 'pagina_documentos' : 'pagina';
        return '?'.htmlspecialchars(http_build_query($parametrosGuardados + [$parametro => $destino]), ENT_QUOTES, 'UTF-8');
    };
?>
<nav class="buzon-sol-paginacion" aria-label="Páginas de documentos">
    <?php if ($pagina_documentos > 1): ?><a class="reference-btn" href="<?=$urlPaginaDocumentos($pagina_documentos-1)?>">Documentos anteriores</a><?php endif; ?>
    <span>Página <?=$pagina_documentos?> de <?=$paginas_documentos?></span>
    <?php if ($pagina_documentos < $paginas_documentos): ?><a class="reference-btn" href="<?=$urlPaginaDocumentos($pagina_documentos+1)?>">Documentos siguientes</a><?php endif; ?>
</nav>
<?php endif; ?>
