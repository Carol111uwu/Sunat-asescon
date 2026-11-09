<?php
declare(strict_types=1);
require_once __DIR__.'/rutas_privadas.php';

function asesconResolverArchivoHistorico(string $ruta): string
{
    $raiz = asesconRaizDatosPrivados();
    static $mapa = null;
    if ($mapa === null) {
        $json = @file_get_contents($raiz . '/almacenamiento/sunat/rutas_archivos_historicos.json');
        $datos = is_string($json) ? json_decode($json, true) : null;
        $mapa = is_array($datos) ? $datos : [];
    }
    $normalizada = str_replace('\\', '/', $ruta);
    $relativa = ltrim($normalizada, '/');
    foreach (['/storage/', '/almacenamiento/'] as $marca) {
        $posicion = strpos($normalizada, $marca);
        if ($posicion !== false) {
            $relativa = substr($normalizada, $posicion + 1);
            break;
        }
    }
    if (!isset($mapa[$relativa])) {
        return $ruta;
    }
    $destino = realpath($raiz . '/' . $mapa[$relativa]);
    $base = realpath($raiz . '/almacenamiento/sunat/sire');
    if ($destino === false || $base === false || !str_starts_with($destino, $base . DIRECTORY_SEPARATOR)) {
        return $ruta;
    }
    return $destino;
}
