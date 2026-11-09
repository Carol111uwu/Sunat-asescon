<?php

declare(strict_types=1);

final class Honorario
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function listar(string $f): array
    {
        $r = $this->conexion->query("SELECT h.*,c.ruc,c.razon_social FROM honorarios h INNER JOIN clientes c ON c.id_cliente=h.id_cliente WHERE {$f} ORDER BY h.estado='PAGADO',h.fecha_vencimiento,h.id_honorario DESC");
        $a = [];
        if ($r) {
            while ($x = $r->fetch_assoc()) {
                $a[] = $x;
            }
        }
return $a;
    }

    public function crear(int $c, string $concepto, float $m, ?string $em, ?string $ven, ?string $obs): void
    {
        $s = $this->conexion->prepare("INSERT INTO honorarios(id_cliente,concepto,monto,fecha_emision,fecha_vencimiento,estado,observaciones) VALUES(?,?,?,?,?,'PENDIENTE',?)");
        $s->bind_param('isdsss', $c, $concepto, $m, $em, $ven, $obs);
        $s->execute();
        $s->close();
    }

    public function pagar(int $id, string $filtroClientes): void
    {
        $s = $this->conexion->prepare("UPDATE honorarios h INNER JOIN clientes c ON c.id_cliente=h.id_cliente SET h.estado='PAGADO',h.fecha_pago=CURDATE() WHERE h.id_honorario=? AND h.estado<>'PAGADO' AND ({$filtroClientes})");
        $s->bind_param('i', $id);
        $s->execute();
        $s->close();
    }
    public function reabrir(int $id,string $filtroClientes):void
    {
        $s=$this->conexion->prepare("UPDATE honorarios h INNER JOIN clientes c ON c.id_cliente=h.id_cliente SET h.estado='PENDIENTE',h.fecha_pago=NULL WHERE h.id_honorario=? AND h.estado='PAGADO' AND ({$filtroClientes})");
        $s->bind_param('i',$id);$s->execute();$s->close();
    }

    public function editar(int $id,string $filtroClientes,string $concepto,float $monto,?string $emision,?string $vencimiento,?string $observaciones):void
    {
        $s=$this->conexion->prepare("UPDATE honorarios h INNER JOIN clientes c ON c.id_cliente=h.id_cliente SET h.concepto=?,h.monto=?,h.fecha_emision=?,h.fecha_vencimiento=?,h.observaciones=? WHERE h.id_honorario=? AND ({$filtroClientes})");
        $s->bind_param('sdsssi',$concepto,$monto,$emision,$vencimiento,$observaciones,$id);
        $s->execute();$s->close();
    }

    public function eliminar(int $id,string $filtroClientes):void
    {
        $s=$this->conexion->prepare("DELETE h FROM honorarios h INNER JOIN clientes c ON c.id_cliente=h.id_cliente WHERE h.id_honorario=? AND ({$filtroClientes})");
        $s->bind_param('i',$id);$s->execute();$n=$s->affected_rows;$s->close();
        if($n!==1)throw new DomainException('Honorario no encontrado o sin acceso.');
    }
}
