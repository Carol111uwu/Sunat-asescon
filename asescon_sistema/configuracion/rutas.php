<?php

declare(strict_types=1);

require_once __DIR__ . '/rutas_compatibilidad.php';

function asesconBaseUrl(): string
{
    $configurada = getenv('ASESCON_BASE_PATH');
    if ($configurada !== false) {
        $ruta = trim($configurada, '/');
        return $ruta === '' ? '' : '/' . $ruta;
    }
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    foreach (['/vistas/', '/controladores/', '/api/', '/views/', '/controllers/'] as $segmento) {
        $posicion = strpos($script, $segmento);
        if ($posicion !== false) {
            return rtrim(substr($script, 0, $posicion), '/');
        }
    }
    $carpeta = str_replace('\\', '/', dirname($script));
    return in_array($carpeta, ['/', '.', '\\'], true) ? '' : rtrim($carpeta, '/');
}

function asesconUrl(string $ruta = ''): string
{
    return asesconBaseUrl() . '/' . asesconNormalizarRuta($ruta);
}

function asesconRedirigir(string $ruta, array $parametros = []): never
{
    $url = asesconUrl($ruta);
    if ($parametros !== []) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($parametros, '', '&', PHP_QUERY_RFC3986);
    }
    header('Location: ' . $url, true, 303);
    exit;
}
