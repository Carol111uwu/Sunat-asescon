<?php

declare(strict_types=1);

final class TipoCambio
{
    public function __construct(private mysqli $conexion)
    {
    }
    public function listar(int $limite = 90): array
    {
        $limite = max(1, min(366, $limite));
        $r = $this->conexion->query("SELECT * FROM tipos_cambio ORDER BY fecha DESC LIMIT {$limite}");
        $a = [];
        if ($r) {
            while ($x = $r->fetch_assoc()) {
                $a[] = $x;
            }
        }
return $a;
    }
    public function guardar(string $fecha, float $compra, float $venta, string $fuente = 'SUNAT'): void
    {
        $s = $this->conexion->prepare("INSERT INTO tipos_cambio(fecha,compra,venta,fuente) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE compra=VALUES(compra),venta=VALUES(venta),fuente=VALUES(fuente)");
        if (!$s) {
            throw new RuntimeException('No se pudo guardar el tipo de cambio.');
        }
$s->bind_param('sdds', $fecha, $compra, $venta, $fuente);
        $s->execute();
        $s->close();
    }
}
