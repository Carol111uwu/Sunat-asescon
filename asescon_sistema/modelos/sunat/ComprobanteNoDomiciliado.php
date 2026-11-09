<?php

declare(strict_types=1);

final class ComprobanteNoDomiciliado
{
    public function __construct(private mysqli $conexion)
    {
    }
    public function listar(string $filtro): array
    {
        $r = $this->conexion->query("SELECT n.*,c.ruc,c.razon_social FROM comprobantes_no_domiciliados n INNER JOIN clientes c ON c.id_cliente=n.id_cliente WHERE {$filtro} ORDER BY n.fecha_emision DESC,n.id_comprobante_no_domiciliado DESC");
        $a = [];
        if ($r) {
            while ($x = $r->fetch_assoc()) {
                $a[] = $x;
            }
        }
return $a;
    }
    public function crear(array $d): int
    {
        $s = $this->conexion->prepare("INSERT INTO comprobantes_no_domiciliados(id_cliente,tipo_comprobante,serie,numero,fecha_emision,proveedor,pais,moneda,importe,impuesto,estado) VALUES(?,?,?,?,?,?,?,?,?,?,?)");
        if (!$s) {
            throw new RuntimeException('No se pudo preparar el comprobante.');
        }
$s->bind_param('isssssssdds', $d['id_cliente'], $d['tipo_comprobante'], $d['serie'], $d['numero'], $d['fecha_emision'], $d['proveedor'], $d['pais'], $d['moneda'], $d['importe'], $d['impuesto'], $d['estado']);
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
