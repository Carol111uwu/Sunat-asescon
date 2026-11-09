<?php

declare(strict_types=1);

require_once __DIR__ . '/../../configuracion/inicializacion.php';
require_once __DIR__ . '/../../modelos/afp/DeclaracionAfp.php';
require_once __DIR__ . '/../../servicios/sunat/PeriodosSire.php';

final class ControladorAFP
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function listar(string $periodo, string $buscar): array
    {
        asesconExigirPermiso($this->conexion, 'AFP', 'ver');
        if ($periodo !== '' && !PeriodosSire::permitido($periodo,false)) {
            throw new InvalidArgumentException('Período AFP inválido.');
        }
        return (new DeclaracionAfp($this->conexion))->listar(asesconFiltroClientesSql($this->conexion, 'c'), $periodo, $buscar);
    }
}
