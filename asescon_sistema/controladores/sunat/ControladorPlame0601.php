<?php

declare(strict_types=1);

require_once __DIR__ . '/../../configuracion/inicializacion.php';
require_once __DIR__ . '/../../modelos/sunat/Plame0601.php';
final class ControladorPlame0601
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function datos(string $periodo = ''): array
    {
        asesconExigirPermiso($this->conexion, 'PLAME_0601', 'ver');
        return ['filas' => (new Plame0601($this->conexion))->listar(asesconFiltroClientesSql($this->conexion, 'c'), $periodo)];
    }
}
