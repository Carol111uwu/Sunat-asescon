<?php

declare(strict_types=1);

require_once __DIR__ . '/rutas_configuracion_privada.php';

function asesconRutaConfiguracionPrivada(): string
{
    return asesconRutaSecretosPrivados();
}

function asesconCargarConfiguracionPrivada(): array
{
    static $configuracion = null;
    if (is_array($configuracion)) {
        return $configuracion;
    }

    $ruta = asesconRutaConfiguracionPrivada();
    if (!is_file($ruta)) {
        return $configuracion = [];
    }

    $datos = require $ruta;
    if (!is_array($datos)) {
        throw new RuntimeException('La configuración privada de ASESCON no es válida.');
    }

    return $configuracion = $datos;
}
