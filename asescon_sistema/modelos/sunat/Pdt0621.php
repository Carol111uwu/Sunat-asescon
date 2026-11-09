<?php

declare(strict_types=1);

final class Pdt0621
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function listar(string $filtro, string $periodo = ''): array
    {
        $extra = '';
        if ($periodo !== '') {
            if (preg_match('/^(20\d{2})00$/', $periodo, $m)) {
                $y = $this->conexion->real_escape_string($m[1]);
                $extra = " AND d.periodo LIKE '" . $y . "%'";
            } else {
                $extra = " AND d.periodo='" . $this->conexion->real_escape_string($periodo) . "'";
            }
        }
$r = $this->conexion->query("SELECT d.*,c.ruc,c.razon_social FROM declaraciones_sunat d INNER JOIN clientes c ON c.id_cliente=d.id_cliente WHERE {$filtro} AND d.formulario='0621'{$extra} ORDER BY d.periodo DESC,c.razon_social");
        $a = [];
        if ($r) {
            while ($x = $r->fetch_assoc()) {
                $a[] = $x;
            }
        }
return $a;
    }
}
