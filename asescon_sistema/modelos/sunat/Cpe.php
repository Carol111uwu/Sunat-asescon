<?php

declare(strict_types=1);

final class Cpe
{
    public function __construct(private mysqli $conexion)
    {
    }
    public function listar(string $filtro): array
    {
        $r = $this->conexion->query("SELECT p.*,c.ruc,c.razon_social FROM cpe p LEFT JOIN clientes c ON c.id_cliente=p.id_cliente WHERE ({$filtro}) ORDER BY p.fecha_consulta DESC,p.id_cpe DESC LIMIT 500");
        $a = [];
        if ($r) {
            while ($x = $r->fetch_assoc()) {
                $a[] = $x;
            }
        }
return $a;
    }
    public function registrar(array $d): int
    {
        $s = $this->conexion->prepare("INSERT INTO cpe(id_cliente,ruc_emisor,tipo_comprobante,serie,numero,fecha_emision,monto_total,moneda,estado_sunat,fecha_consulta) VALUES(?,?,?,?,?,?,?,?,'PENDIENTE_CONSULTA',NOW())");
        if (!$s) {
            throw new RuntimeException('No se pudo registrar la consulta CPE.');
        }
        $s->bind_param('isssssds', $d['id_cliente'], $d['ruc_emisor'], $d['tipo_comprobante'], $d['serie'], $d['numero'], $d['fecha_emision'], $d['monto_total'], $d['moneda']);
        if (!$s->execute()) {
            $e = $s->error;
            $s->close();
            throw new RuntimeException($e);
        }
        $id = (int)$this->conexion->insert_id;
        $s->close();
        return $id;
    }
}
