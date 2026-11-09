<?php

declare(strict_types=1);

final class ReciboHonorario
{
    public function __construct(private mysqli $conexion)
    {
    }
    public function listar(string $filtro): array
    {
        $r = $this->conexion->query("SELECT h.*,c.ruc,c.razon_social FROM recibos_honorarios h INNER JOIN clientes c ON c.id_cliente=h.id_cliente WHERE {$filtro} ORDER BY h.fecha_emision DESC,h.id_recibo_honorario DESC");
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
        $s = $this->conexion->prepare("INSERT INTO recibos_honorarios(id_cliente,numero_recibo,fecha_emision,fecha_pago,monto_bruto,retencion,monto_neto,estado,descripcion) VALUES(?,?,?,?,?,?,?,?,?)");
        if (!$s) {
            throw new RuntimeException('No se pudo preparar el recibo.');
        }
$s->bind_param('isssdddss', $d['id_cliente'], $d['numero_recibo'], $d['fecha_emision'], $d['fecha_pago'], $d['monto_bruto'], $d['retencion'], $d['monto_neto'], $d['estado'], $d['descripcion']);
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
