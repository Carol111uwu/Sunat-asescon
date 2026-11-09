<?php

declare(strict_types=1);

require_once __DIR__ . '/../../configuracion/inicializacion.php';
require_once __DIR__ . '/../../modelos/sunat/Pdt0621.php';
final class ControladorPdt0621
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function datos(string $periodo = ''): array
    {
        asesconExigirPermiso($this->conexion, 'PDT_0621', 'ver');
        return ['filas' => (new Pdt0621($this->conexion))->listar(asesconFiltroClientesSql($this->conexion, 'c'), $periodo)];
    }
}
